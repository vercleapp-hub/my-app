export default async function handler(req, res) {
  if (req.method !== 'POST') { res.status(405).json({ error: 'method_not_allowed' }); return; }
  const body = req.body || {};
  const url = process.env.SUPABASE_URL;
  const key = process.env.SUPABASE_SERVICE_ROLE_KEY || process.env.SUPABASE_ANON_KEY;
  const row = {
    user_id: null,
    session_id: body.session_id || null,
    ip_address: req.headers['x-forwarded-for']?.split(',')[0]?.trim() || req.socket?.remoteAddress || null,
    user_agent: req.headers['user-agent'] || null,
    payload: body
  };
  const ins = await fetch(`${url}/rest/v1/device_logs`, {
    method: 'POST',
    headers: { apikey: key, Authorization: `Bearer ${key}`, 'Content-Type': 'application/json' },
    body: JSON.stringify(row)
  });
  const data = await ins.json();
  if (!ins.ok) { res.status(500).json({ error: 'db_insert_failed', detail: data }); return; }
  res.json({ ok: true });
}
