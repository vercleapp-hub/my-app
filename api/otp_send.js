export default async function handler(req, res) {
  if (req.method !== 'POST') {
    res.status(405).json({ error: 'method_not_allowed' }); return;
  }
  const body = req.body || {};
  const phone = (body.phone || '').toString().trim();
  const purpose = (body.purpose || 'login').toString();
  if (!/^\+?\d{10,15}$/.test(phone)) { res.status(422).json({ error: 'phone_invalid' }); return; }
  const code = Math.floor(100000 + Math.random() * 900000).toString();
  const expiresAt = new Date(Date.now() + 10 * 60 * 1000).toISOString();
  const url = process.env.SUPABASE_URL;
  const key = process.env.SUPABASE_SERVICE_ROLE_KEY || process.env.SUPABASE_ANON_KEY;
  const ulInstance = process.env.ULTRAMSG_INSTANCE_ID;
  const ulToken = process.env.ULTRAMSG_TOKEN;
  const ins = await fetch(`${url}/rest/v1/otp_codes`, {
    method: 'POST',
    headers: { apikey: key, Authorization: `Bearer ${key}`, 'Content-Type': 'application/json', Prefer: 'return=representation' },
    body: JSON.stringify([{ phone, code, purpose, attempts: 0, max_attempts: 5, expires_at: expiresAt, verified: false }])
  });
  const insData = await ins.json();
  if (!ins.ok) { res.status(500).json({ error: 'db_insert_failed', detail: insData }); return; }
  const bodyText = `رمز التحقق: ${code} (صالح لمدة 10 دقائق)`;
  const send = await fetch(`https://api.ultramsg.com/${ulInstance}/messages/chat`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ token: ulToken, to: phone, body: bodyText, priority: '0', referenceId: 'OTP' })
  });
  const sendText = await send.text();
  if (!send.ok) { res.status(502).json({ error: 'send_failed', response: sendText }); return; }
  res.json({ status: 'sent', id: insData?.[0]?.id || null });
}
