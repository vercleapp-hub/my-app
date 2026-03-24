export default async function handler(req, res) {
  if (req.method !== 'POST') { res.status(405).json({ error: 'method_not_allowed' }); return; }
  const body = req.body || {};
  const phone = (body.phone || '').toString().trim();
  const code = (body.code || '').toString().trim();
  if (!/^\+?\d{10,15}$/.test(phone) || !/^\d{4,8}$/.test(code)) { res.status(422).json({ valid: false, reason: 'invalid' }); return; }
  const url = process.env.SUPABASE_URL;
  const key = process.env.SUPABASE_SERVICE_ROLE_KEY || process.env.SUPABASE_ANON_KEY;
  const sel = await fetch(`${url}/rest/v1/otp_codes?phone=eq.${encodeURIComponent(phone)}&code=eq.${encodeURIComponent(code)}&order=id.desc&limit=1`, {
    headers: { apikey: key, Authorization: `Bearer ${key}` }
  });
  const rows = await sel.json();
  const rec = rows?.[0];
  if (!sel.ok || !rec) { res.status(404).json({ valid: false, reason: 'not_found' }); return; }
  if (rec.verified) { res.status(409).json({ valid: false, reason: 'already_verified' }); return; }
  if (rec.attempts >= rec.max_attempts) { res.status(429).json({ valid: false, reason: 'max_attempts' }); return; }
  if (new Date(rec.expires_at).getTime() < Date.now()) { res.status(410).json({ valid: false, reason: 'expired' }); return; }
  await fetch(`${url}/rest/v1/otp_codes?id=eq.${rec.id}`, {
    method: 'PATCH',
    headers: { apikey: key, Authorization: `Bearer ${key}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ attempts: rec.attempts + 1, verified: true })
  });
  res.json({ valid: true });
}
