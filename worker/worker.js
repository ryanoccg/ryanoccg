/**
 * Ryano Chu — Quote Request Form Worker
 *
 * Receives form submissions from ryanoccg.com, validates them
 * (CORS, Turnstile, honeypot, rate limit), then sends an email
 * via SMTP2GO API.
 *
 * Required environment variables (set in Cloudflare dashboard):
 *   - SMTP2GO_API_KEY       SMTP2GO API key
 *   - SMTP2GO_SENDER        Verified sender email (e.g. noreply@ryanoccg.com)
 *   - NOTIFY_TO             Your inbox (e.g. ryanoccg@gmail.com)
 *   - TURNSTILE_SECRET      Cloudflare Turnstile secret key
 *
 * Required KV binding (optional, for rate limiting):
 *   - RATE_LIMIT            KV namespace
 */

const ALLOWED_ORIGINS = [
  'https://ryanoccg.com',
  'https://www.ryanoccg.com',
  'http://127.0.0.1:8080',
  'http://localhost:8080',
];

const CORS_HEADERS = (origin) => ({
  'Access-Control-Allow-Origin': ALLOWED_ORIGINS.includes(origin) ? origin : ALLOWED_ORIGINS[0],
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type',
  'Access-Control-Max-Age': '86400',
  'Vary': 'Origin',
});

const json = (data, status, origin) =>
  new Response(JSON.stringify(data), {
    status,
    headers: {
      'Content-Type': 'application/json',
      ...CORS_HEADERS(origin),
    },
  });

const escapeHtml = (str) =>
  String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const isEmail = (str) =>
  /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(str || '').trim());

async function verifyTurnstile(token, secret, ip) {
  if (!token || !secret) return false;
  const form = new FormData();
  form.append('secret', secret);
  form.append('response', token);
  if (ip) form.append('remoteip', ip);

  try {
    const res = await fetch('https://challenges.cloudflare.com/turnstile/v0/siteverify', {
      method: 'POST',
      body: form,
    });
    const data = await res.json();
    return !!data.success;
  } catch {
    return false;
  }
}

async function checkRateLimit(env, ip) {
  if (!env.RATE_LIMIT) return { ok: true };
  const key = `rl:${ip}`;
  const current = await env.RATE_LIMIT.get(key);
  const count = current ? parseInt(current, 10) : 0;
  if (count >= 3) return { ok: false, remaining: 0 };
  await env.RATE_LIMIT.put(key, String(count + 1), { expirationTtl: 3600 });
  return { ok: true, remaining: 3 - count - 1 };
}

async function sendEmail(env, payload) {
  const {
    name, email, phone, pkg, budget, message, ip, userAgent,
  } = payload;

  const textBody = [
    `New quote request from ryanoccg.com`,
    ``,
    `Name:    ${name}`,
    `Email:   ${email}`,
    `Phone:   ${phone || '—'}`,
    `Package: ${pkg}`,
    `Budget:  ${budget || '—'}`,
    ``,
    `Message:`,
    `${message}`,
    ``,
    `──────────────`,
    `IP: ${ip}`,
    `UA: ${userAgent}`,
    `Submitted: ${new Date().toISOString()}`,
  ].join('\n');

  const htmlBody = `
    <div style="font-family:system-ui,-apple-system,Segoe UI,sans-serif;max-width:600px;margin:0 auto;">
      <div style="background:#069AFF;color:#fff;padding:20px;border-radius:8px 8px 0 0;">
        <h2 style="margin:0;">New Quote Request</h2>
        <p style="margin:4px 0 0;opacity:.9;">from ryanoccg.com</p>
      </div>
      <div style="border:1px solid #eee;border-top:none;padding:20px;border-radius:0 0 8px 8px;">
        <table style="width:100%;border-collapse:collapse;">
          <tr><td style="padding:8px 0;color:#666;width:100px;">Name</td><td style="padding:8px 0;font-weight:600;">${escapeHtml(name)}</td></tr>
          <tr><td style="padding:8px 0;color:#666;">Email</td><td style="padding:8px 0;"><a href="mailto:${escapeHtml(email)}">${escapeHtml(email)}</a></td></tr>
          <tr><td style="padding:8px 0;color:#666;">Phone</td><td style="padding:8px 0;">${escapeHtml(phone || '—')}</td></tr>
          <tr><td style="padding:8px 0;color:#666;">Package</td><td style="padding:8px 0;font-weight:600;color:#069AFF;">${escapeHtml(pkg)}</td></tr>
          <tr><td style="padding:8px 0;color:#666;">Budget</td><td style="padding:8px 0;">${escapeHtml(budget || '—')}</td></tr>
        </table>
        <div style="margin-top:16px;padding:16px;background:#f7f7f7;border-radius:6px;">
          <div style="color:#666;font-size:13px;margin-bottom:6px;">Message</div>
          <div style="white-space:pre-wrap;">${escapeHtml(message)}</div>
        </div>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid #eee;font-size:12px;color:#999;">
          IP: ${escapeHtml(ip)}<br>
          UA: ${escapeHtml(userAgent)}<br>
          Time: ${new Date().toISOString()}
        </div>
      </div>
    </div>
  `;

  const res = await fetch('https://api.smtp2go.com/v3/email/send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      api_key: env.SMTP2GO_API_KEY,
      to: [env.NOTIFY_TO],
      sender: env.SMTP2GO_SENDER,
      reply_to: email,
      subject: `[Quote] ${name} — ${pkg}`,
      text_body: textBody,
      html_body: htmlBody,
    }),
  });

  const data = await res.json().catch(() => ({}));
  if (!res.ok || data?.data?.error) {
    throw new Error(data?.data?.error || `SMTP2GO error ${res.status}`);
  }
  return data;
}

export default {
  async fetch(request, env) {
    const origin = request.headers.get('Origin') || '';

    if (request.method === 'OPTIONS') {
      return new Response(null, { status: 204, headers: CORS_HEADERS(origin) });
    }

    if (request.method !== 'POST') {
      return json({ error: 'Method not allowed' }, 405, origin);
    }

    if (!ALLOWED_ORIGINS.includes(origin)) {
      return json({ error: 'Forbidden origin' }, 403, origin);
    }

    const ip = request.headers.get('CF-Connecting-IP') || 'unknown';
    const userAgent = request.headers.get('User-Agent') || 'unknown';

    // Rate limit: 3 submissions per IP per hour
    const rate = await checkRateLimit(env, ip);
    if (!rate.ok) {
      return json({ error: 'Too many requests. Please try again later.' }, 429, origin);
    }

    let body;
    try {
      const ct = request.headers.get('Content-Type') || '';
      if (ct.includes('application/json')) {
        body = await request.json();
      } else {
        const form = await request.formData();
        body = Object.fromEntries(form.entries());
      }
    } catch {
      return json({ error: 'Invalid request body' }, 400, origin);
    }

    // Honeypot: bots fill hidden fields
    if (body._gotcha) {
      return json({ ok: true }, 200, origin); // fake success
    }

    // Turnstile verification
    const turnstileOk = await verifyTurnstile(
      body['cf-turnstile-response'],
      env.TURNSTILE_SECRET,
      ip
    );
    if (!turnstileOk) {
      return json({ error: 'Verification failed. Please retry.' }, 400, origin);
    }

    // Field validation
    const name = String(body.name || '').trim();
    const email = String(body.email || '').trim();
    const phone = String(body.phone || '').trim();
    const pkg = String(body.package || '').trim();
    const budget = String(body.budget || '').trim();
    const message = String(body.message || '').trim();

    if (!name || name.length > 100) {
      return json({ error: 'Name is required (max 100 chars)' }, 400, origin);
    }
    if (!isEmail(email)) {
      return json({ error: 'Valid email is required' }, 400, origin);
    }
    if (!pkg) {
      return json({ error: 'Please select a package' }, 400, origin);
    }
    if (!message || message.length < 10 || message.length > 5000) {
      return json({ error: 'Message must be 10 to 5000 characters' }, 400, origin);
    }

    try {
      await sendEmail(env, {
        name, email, phone, pkg, budget, message, ip, userAgent,
      });
      return json({ ok: true, message: 'Thanks! I\'ll reply within 24 hours.' }, 200, origin);
    } catch (err) {
      console.error('SMTP2GO error:', err.message);
      return json({ error: 'Failed to send. Please email ryanoccg@gmail.com directly.' }, 502, origin);
    }
  },
};
