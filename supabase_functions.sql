-- ========================================================
-- 1. Atomic Wallet Balance Increment
-- ========================================================
CREATE OR REPLACE FUNCTION increment_wallet_balance(
  p_user_id UUID,
  p_amount DECIMAL
) RETURNS VOID AS $$
BEGIN
  INSERT INTO wallets (user_id, balance)
  VALUES (p_user_id, p_amount)
  ON CONFLICT (user_id)
  DO UPDATE SET balance = wallets.balance + p_amount;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ========================================================
-- 2. Handle Scratch Card Purchase (Atomic)
-- ========================================================
CREATE OR REPLACE FUNCTION handle_scratch_card_purchase(
  p_service_id UUID,
  p_amount DECIMAL
) RETURNS JSONB AS $$
DECLARE
  v_user_id UUID := auth.uid();
  v_balance DECIMAL;
  v_card_record RECORD;
  v_invoice_id UUID;
  v_service_name TEXT;
BEGIN
  -- 1. Check balance
  SELECT balance INTO v_balance FROM wallets WHERE user_id = v_user_id FOR UPDATE;
  IF v_balance < p_amount THEN
    RAISE EXCEPTION 'Insufficient balance';
  END IF;

  -- 2. Fetch service name
  SELECT name INTO v_service_name FROM services WHERE id = p_service_id;

  -- 3. Pick a card (FIFO)
  SELECT * INTO v_card_record 
  FROM scratch_cards 
  WHERE category = (SELECT service_code FROM services WHERE id = p_service_id)
    AND is_sold = FALSE 
  ORDER BY created_at ASC 
  LIMIT 1 FOR UPDATE SKIP LOCKED;

  IF NOT FOUND THEN
    RAISE EXCEPTION 'OUT_OF_STOCK';
  END IF;

  -- 4. Deduct balance
  UPDATE wallets SET balance = balance - p_amount WHERE user_id = v_user_id;

  -- 5. Mark card as sold
  UPDATE scratch_cards 
  SET is_sold = TRUE, sold_to = v_user_id, sold_at = NOW() 
  WHERE id = v_card_record.id;

  -- 6. Create Transaction/Invoice record
  INSERT INTO transactions (
    user_id, 
    service_id, 
    service_name, 
    amount, 
    total, 
    status, 
    paid_at,
    service_data
  )
  VALUES (
    v_user_id, 
    p_service_id, 
    v_service_name, 
    p_amount, 
    p_amount, 
    'paid', 
    NOW(),
    jsonb_build_object('card_code', v_card_record.code, 'serial_number', v_card_record.serial_number)
  )
  RETURNING id INTO v_invoice_id;

  RETURN jsonb_build_object(
    'invoice_id', v_invoice_id,
    'card_code', v_card_record.code,
    'service_name', v_service_name
  );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ========================================================
-- 3. Atomic Financial Core (Production Ready)
-- ========================================================

-- Atomic Wallet Deduction & Transaction Initialization
CREATE OR REPLACE FUNCTION process_payment_atomic(
  p_user_id UUID,
  p_service_id UUID,
  p_amount DECIMAL,
  p_api_id TEXT,
  p_customer_phone TEXT,
  p_service_name TEXT,
  p_service_data JSONB,
  p_request_hash TEXT
) RETURNS UUID AS $$
DECLARE
  v_balance DECIMAL;
  v_transaction_id UUID;
BEGIN
  -- 1. Idempotency Check (Check both API ID and Request Hash)
  IF EXISTS (SELECT 1 FROM transactions WHERE api_id = p_api_id OR request_hash = p_request_hash) THEN
    SELECT id INTO v_transaction_id FROM transactions 
    WHERE api_id = p_api_id OR request_hash = p_request_hash
    ORDER BY created_at DESC LIMIT 1;
    RETURN v_transaction_id;
  END IF;

  -- 2. Lock User Wallet for Update
  SELECT balance INTO v_balance FROM wallets WHERE user_id = p_user_id FOR UPDATE;
  
  -- 3. Check Balance
  IF v_balance < p_amount THEN
    RAISE EXCEPTION 'Insufficient balance';
  END IF;

  -- 4. Deduct Balance
  UPDATE wallets SET balance = balance - p_amount WHERE user_id = p_user_id;

  -- 5. Create Transaction Record (payment_pending)
  INSERT INTO transactions (
    user_id, 
    service_id, 
    service_name, 
    customer_phone,
    amount, 
    total, 
    status,
    api_id,
    request_hash,
    service_data
  )
  VALUES (
    p_user_id, 
    p_service_id, 
    p_service_name, 
    p_customer_phone,
    p_amount, 
    p_amount, 
    'payment_pending',
    p_api_id,
    p_request_hash,
    p_service_data
  )
  RETURNING id INTO v_transaction_id;

  -- 6. Log Initial Event
  INSERT INTO payment_events (transaction_id, event_type, payload)
  VALUES (v_transaction_id, 'initialization', jsonb_build_object('api_id', p_api_id, 'amount', p_amount));

  RETURN v_transaction_id;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Atomic Refund in case of Gateway Failure
CREATE OR REPLACE FUNCTION refund_transaction(
  p_transaction_id UUID,
  p_admin_notes TEXT
) RETURNS VOID AS $$
DECLARE
  v_user_id UUID;
  v_amount DECIMAL;
  v_status TEXT;
BEGIN
  SELECT user_id, amount, status INTO v_user_id, v_amount, v_status FROM transactions WHERE id = p_transaction_id FOR UPDATE;
  
  IF v_status <> 'payment_pending' AND v_status <> 'failed' THEN
    RAISE EXCEPTION 'Transaction cannot be refunded in current status: %', v_status;
  END IF;

  -- Refund Wallet
  UPDATE wallets SET balance = balance + v_amount WHERE user_id = v_user_id;

  -- Update Transaction Status
  UPDATE transactions SET status = 'reversed', admin_notes = p_admin_notes WHERE id = p_transaction_id;

  -- Log Refund Event
  INSERT INTO payment_events (transaction_id, event_type, payload)
  VALUES (p_transaction_id, 'refund', jsonb_build_object('reason', p_admin_notes, 'amount', v_amount));
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ========================================================
-- 4. Schema Updates for Production-Readiness
-- ========================================================

-- Update Transactions table
ALTER TABLE transactions 
ADD COLUMN IF NOT EXISTS gateway_status TEXT,
ADD COLUMN IF NOT EXISTS api_id TEXT,
ADD COLUMN IF NOT EXISTS request_hash TEXT,
ADD COLUMN IF NOT EXISTS gateway_response JSONB,
ADD COLUMN IF NOT EXISTS admin_notes TEXT;

-- Update Services table
ALTER TABLE services
ADD COLUMN IF NOT EXISTS gateway_code TEXT,
ADD COLUMN IF NOT EXISTS gateway_service_id TEXT,
ADD COLUMN IF NOT EXISTS requires_inquiry BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}'::jsonb;

-- Create Payment Events table for detailed logging
CREATE TABLE IF NOT EXISTS payment_events (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    transaction_id UUID REFERENCES transactions(id),
    event_type TEXT, -- 'request', 'response', 'error'
    gateway_name TEXT DEFAULT 'cashmisr',
    payload JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Ensure Unique Constraints for Idempotency (Strict Enforcement)
DO $$ 
BEGIN 
    -- 1. Unique constraint for API ID
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'unique_api_id') THEN
        ALTER TABLE transactions ADD CONSTRAINT unique_api_id UNIQUE (api_id);
    END IF;

    -- 2. Unique constraint for Request Hash (Client-side fingerprint)
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'unique_request_hash') THEN
        ALTER TABLE transactions ADD CONSTRAINT unique_request_hash UNIQUE (request_hash);
    END IF;
END $$;

-- Enhanced logging helper for production debugging
CREATE OR REPLACE FUNCTION log_payment_event(
  p_transaction_id UUID,
  p_event_type TEXT,
  p_payload JSONB,
  p_gateway TEXT DEFAULT 'cashmisr'
) RETURNS VOID AS $$
BEGIN
  INSERT INTO payment_events (transaction_id, event_type, payload, gateway_name)
  VALUES (p_transaction_id, p_event_type, p_payload, p_gateway);
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ========================================================
-- 5. Missing Tables & RPCs (Deposits & Support)
-- ========================================================

-- Deposit Methods
CREATE TABLE IF NOT EXISTS deposit_methods (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name TEXT NOT NULL,
    details TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Deposit Requests
CREATE TABLE IF NOT EXISTS deposit_requests (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID REFERENCES custom_users(id),
    user_name TEXT,
    amount DECIMAL NOT NULL,
    method_id UUID REFERENCES deposit_methods(id),
    method_name TEXT,
    transaction_ref TEXT,
    status TEXT DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    reviewer_id UUID REFERENCES custom_users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Complaints / Support Tickets
CREATE TABLE IF NOT EXISTS complaints (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID REFERENCES custom_users(id),
    user_name TEXT,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    related_invoice_id UUID REFERENCES transactions(id),
    status TEXT DEFAULT 'open', -- 'open', 'in-progress', 'closed'
    resolution TEXT,
    admin_notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Atomic Deposit Handling RPC
CREATE OR REPLACE FUNCTION handle_deposit_request(
  p_request_id UUID,
  p_action TEXT, -- 'approve', 'reject'
  p_reviewer_id UUID
) RETURNS VOID AS $$
DECLARE
  v_user_id UUID;
  v_amount DECIMAL;
  v_status TEXT;
BEGIN
  -- Lock request
  SELECT user_id, amount, status INTO v_user_id, v_amount, v_status 
  FROM deposit_requests WHERE id = p_request_id FOR UPDATE;

  IF v_status <> 'pending' THEN
    RAISE EXCEPTION 'Request already processed.';
  END IF;

  IF p_action = 'approve' THEN
    -- Update Wallet
    UPDATE wallets SET balance = balance + v_amount WHERE user_id = v_user_id;
    
    -- Record internal transaction for accounting
    INSERT INTO transactions (user_id, service_id, amount, status, service_name, customer_phone)
    VALUES (v_user_id, NULL, v_amount, 'paid', 'إيداع رصيد', 'System');

    UPDATE deposit_requests SET status = 'approved', reviewer_id = p_reviewer_id, updated_at = NOW() WHERE id = p_request_id;
  ELSE
    UPDATE deposit_requests SET status = 'rejected', reviewer_id = p_reviewer_id, updated_at = NOW() WHERE id = p_request_id;
  END IF;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Manual Bill Approval by Admin
CREATE OR REPLACE FUNCTION handle_manual_bill_approval(
  p_invoice_id UUID,
  p_reference_code TEXT
) RETURNS VOID AS $$
BEGIN
  UPDATE transactions SET 
    status = 'paid', 
    provider_transaction_id = p_reference_code,
    paid_at = NOW(),
    gateway_status = 'MANUAL_SUCCESS'
  WHERE id = p_invoice_id;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Manual Scratch Card Approval by Admin
CREATE OR REPLACE FUNCTION handle_admin_scratch_card_approval(
  p_invoice_id UUID
) RETURNS TEXT AS $$
DECLARE
  v_card_code TEXT;
  v_transaction_id UUID;
  v_service_id UUID;
BEGIN
  -- Get transaction details
  SELECT id, service_id INTO v_transaction_id, v_service_id FROM transactions WHERE id = p_invoice_id FOR UPDATE;

  -- Pick a card
  SELECT code INTO v_card_code FROM scratch_cards 
  WHERE service_id = v_service_id AND is_sold = false 
  ORDER BY created_at ASC LIMIT 1 FOR UPDATE;

  IF v_card_code IS NULL THEN
    RAISE EXCEPTION 'OUT_OF_STOCK';
  END IF;

  -- Mark card as sold
  UPDATE scratch_cards SET is_sold = true, sold_at = NOW(), transaction_id = v_transaction_id WHERE code = v_card_code;

  -- Update transaction
  UPDATE transactions SET 
    status = 'paid', 
    provider_transaction_id = v_card_code,
    paid_at = NOW()
  WHERE id = v_transaction_id;

  RETURN v_card_code;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Branches and Employees (Simple Placeholder Schema)
CREATE TABLE IF NOT EXISTS branches (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name TEXT NOT NULL,
    location TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS employees (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name TEXT NOT NULL,
    role TEXT,
    branch_id UUID REFERENCES branches(id),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
