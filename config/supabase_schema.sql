CREATE TABLE IF NOT EXISTS users (
  id BIGSERIAL PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(100),
  role VARCHAR(20) NOT NULL DEFAULT 'user',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_active BOOLEAN DEFAULT TRUE,
  failed_attempts INT DEFAULT 0,
  lock_until TIMESTAMPTZ,
  balance NUMERIC(12,2) DEFAULT 0,
  last_login TIMESTAMPTZ,
  last_ip VARCHAR(45),
  session_token VARCHAR(255),
  last_latitude NUMERIC(10,8),
  last_longitude NUMERIC(11,8),
  last_location_name VARCHAR(255),
  last_user_agent TEXT,
  login_count INT DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);

CREATE TABLE IF NOT EXISTS permission_modules (
  id BIGSERIAL PRIMARY KEY,
  module_name VARCHAR(100) UNIQUE NOT NULL,
  module_description TEXT,
  module_icon VARCHAR(50),
  parent_module VARCHAR(100),
  display_order INT DEFAULT 0,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS site_permissions (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
  permission_name VARCHAR(100) NOT NULL,
  permission_description TEXT,
  permission_value BOOLEAN DEFAULT TRUE,
  module VARCHAR(50),
  granted_at TIMESTAMPTZ DEFAULT NOW(),
  granted_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
  expires_at TIMESTAMPTZ,
  is_active BOOLEAN DEFAULT TRUE
);

CREATE INDEX IF NOT EXISTS idx_site_permissions_user_perm ON site_permissions(user_id, permission_name);
CREATE INDEX IF NOT EXISTS idx_site_permissions_active ON site_permissions(is_active);

CREATE TABLE IF NOT EXISTS permission_logs (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
  permission_name VARCHAR(100),
  action VARCHAR(50),
  resource VARCHAR(100),
  ip_address VARCHAR(45),
  user_agent TEXT,
  success BOOLEAN DEFAULT TRUE,
  error_message TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_permission_logs_user_action ON permission_logs(user_id, action);
CREATE INDEX IF NOT EXISTS idx_permission_logs_created ON permission_logs(created_at);

CREATE TABLE IF NOT EXISTS login_sessions (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
  session_id VARCHAR(255) NOT NULL,
  device_fingerprint VARCHAR(64),
  ip_address VARCHAR(45) NOT NULL,
  user_agent TEXT,
  device_info JSONB,
  network_info JSONB,
  location_info JSONB,
  hardware_info JSONB,
  manufacturer VARCHAR(100),
  browser VARCHAR(50),
  os VARCHAR(50),
  screen_info VARCHAR(100),
  isp VARCHAR(100),
  permissions_used JSONB,
  login_time TIMESTAMPTZ DEFAULT NOW(),
  last_activity TIMESTAMPTZ DEFAULT NOW(),
  logout_time TIMESTAMPTZ,
  session_duration INT,
  is_active BOOLEAN DEFAULT TRUE
);

CREATE INDEX IF NOT EXISTS idx_login_sessions_user_session ON login_sessions(user_id, session_id);
CREATE INDEX IF NOT EXISTS idx_login_sessions_active ON login_sessions(is_active);
CREATE INDEX IF NOT EXISTS idx_login_sessions_login_time ON login_sessions(login_time);

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGSERIAL PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempt_time TIMESTAMPTZ DEFAULT NOW(),
  successful BOOLEAN DEFAULT FALSE,
  user_agent TEXT
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_username ON login_attempts(username);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address);
CREATE INDEX IF NOT EXISTS idx_login_attempts_time ON login_attempts(attempt_time);
CREATE INDEX IF NOT EXISTS idx_login_attempts_success ON login_attempts(successful);

CREATE TABLE IF NOT EXISTS otp_codes (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  phone VARCHAR(30) NOT NULL,
  code VARCHAR(10) NOT NULL,
  purpose VARCHAR(30) DEFAULT 'login',
  attempts INT DEFAULT 0,
  max_attempts INT DEFAULT 5,
  expires_at TIMESTAMPTZ NOT NULL,
  verified BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  session_id VARCHAR(255),
  device_fingerprint VARCHAR(64)
);

CREATE INDEX IF NOT EXISTS idx_otp_phone ON otp_codes(phone);
CREATE INDEX IF NOT EXISTS idx_otp_code ON otp_codes(code);
CREATE INDEX IF NOT EXISTS idx_otp_exp ON otp_codes(expires_at);
CREATE INDEX IF NOT EXISTS idx_otp_verified ON otp_codes(verified);

CREATE TABLE IF NOT EXISTS device_logs (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  session_id VARCHAR(255),
  ip_address VARCHAR(45),
  user_agent TEXT,
  payload JSONB,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_device_logs_user_session ON device_logs(user_id, session_id);
CREATE INDEX IF NOT EXISTS idx_device_logs_created ON device_logs(created_at);

CREATE TABLE IF NOT EXISTS services (
  id BIGSERIAL PRIMARY KEY,
  service_name VARCHAR(255) NOT NULL,
  description TEXT,
  price NUMERIC(12,2) DEFAULT 0,
  fee NUMERIC(12,2) DEFAULT 0,
  merchant_profit NUMERIC(12,2) DEFAULT 0,
  status BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_services_name ON services(service_name);
CREATE INDEX IF NOT EXISTS idx_services_status ON services(status);

CREATE TABLE IF NOT EXISTS clients (
  id BIGSERIAL PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  national_id VARCHAR(14) NOT NULL UNIQUE,
  birthdate DATE,
  phone_main VARCHAR(20) NOT NULL,
  phone_extra VARCHAR(20),
  governorate VARCHAR(50),
  landline VARCHAR(20),
  service_number VARCHAR(100),
  service_details TEXT,
  service_id BIGINT REFERENCES services(id) ON DELETE SET NULL,
  address TEXT,
  notes TEXT,
  id_front VARCHAR(255),
  id_back VARCHAR(255),
  service_image VARCHAR(255),
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_clients_phone_main ON clients(phone_main);
CREATE INDEX IF NOT EXISTS idx_clients_service ON clients(service_id);

CREATE TABLE IF NOT EXISTS operations (
  id BIGSERIAL PRIMARY KEY,
  client_id BIGINT REFERENCES clients(id) ON DELETE SET NULL,
  service_id BIGINT REFERENCES services(id) ON DELETE SET NULL,
  service_name VARCHAR(255),
  invoice_no VARCHAR(100),
  service_number VARCHAR(100),
  details TEXT,
  notes TEXT,
  amount NUMERIC(12,2) DEFAULT 0,
  fees NUMERIC(12,2) DEFAULT 0,
  total NUMERIC(12,2) DEFAULT 0,
  status VARCHAR(20) DEFAULT 'pending',
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_operations_client ON operations(client_id);
CREATE INDEX IF NOT EXISTS idx_operations_service ON operations(service_id);
CREATE INDEX IF NOT EXISTS idx_operations_status ON operations(status);
CREATE INDEX IF NOT EXISTS idx_operations_invoice ON operations(invoice_no);

CREATE TABLE IF NOT EXISTS transactions (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
  service_id BIGINT REFERENCES services(id) ON DELETE SET NULL,
  amount NUMERIC(12,2) NOT NULL,
  data JSONB,
  reference VARCHAR(100),
  status VARCHAR(20) DEFAULT 'pending',
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_transactions_service ON transactions(service_id);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_transactions_reference ON transactions(reference);

CREATE TABLE IF NOT EXISTS deposits (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
  amount NUMERIC(12,2) NOT NULL,
  method VARCHAR(50),
  receipt VARCHAR(255),
  status VARCHAR(20) DEFAULT 'pending',
  created_at TIMESTAMPTZ DEFAULT NOW(),
  approved_at TIMESTAMPTZ,
  approved_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
  rejected_at TIMESTAMPTZ,
  rejection_reason TEXT
);

CREATE INDEX IF NOT EXISTS idx_deposits_user ON deposits(user_id);
CREATE INDEX IF NOT EXISTS idx_deposits_status ON deposits(status);
CREATE INDEX IF NOT EXISTS idx_deposits_method ON deposits(method);

CREATE TABLE IF NOT EXISTS deposit_methods (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(100) UNIQUE NOT NULL,
  active BOOLEAN DEFAULT TRUE,
  details JSONB
);

CREATE TABLE IF NOT EXISTS notifications (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
  title VARCHAR(255),
  body TEXT,
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read);

CREATE TABLE IF NOT EXISTS agent_commissions (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
  service_id BIGINT REFERENCES services(id) ON DELETE SET NULL,
  percentage NUMERIC(5,2),
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS service_fields (
  id BIGSERIAL PRIMARY KEY,
  service_id BIGINT REFERENCES services(id) ON DELETE CASCADE,
  name VARCHAR(100) NOT NULL,
  label VARCHAR(255),
  required BOOLEAN DEFAULT FALSE,
  input_type VARCHAR(50) DEFAULT 'text',
  created_at TIMESTAMPTZ DEFAULT NOW()
);
ALTER TABLE device_logs ENABLE ROW LEVEL SECURITY;
CREATE POLICY device_logs_insert_public ON device_logs FOR INSERT WITH CHECK (true);
ALTER TABLE otp_codes ENABLE ROW LEVEL SECURITY;
