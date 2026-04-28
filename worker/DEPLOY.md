# Quote Form Deployment Guide

Stack: Cloudflare Worker + SMTP2GO + Cloudflare Turnstile  
Domain DNS: Cloudflare (ryanoccg.com)  
Website files: cPanel (unchanged — Worker is independent)

---

## Step 1 — SMTP2GO Setup (5 min)

1. Sign up at https://www.smtp2go.com (free: 1,000 emails/month)
2. **Settings → API Keys → Add API Key**
   - Name: `ryanoccg-worker`
   - Permission: **Email Send** only
   - Copy the key (starts with `api-...`)
3. **Sender Domains → Add Sender Domain** → enter `ryanoccg.com`
4. SMTP2GO shows you DNS records to add — keep tab open for Step 2

---

## Step 2 — DNS Records in Cloudflare (5 min)

Go to **Cloudflare Dashboard → ryanoccg.com → DNS → Records**

### SPF Record

Check if a TXT record for `@` already exists with `v=spf1`:
- **If no SPF yet** → Add new record:
  ```
  Type:  TXT
  Name:  @
  Value: v=spf1 include:smtp2go.com ~all
  TTL:   Auto
  Proxy: DNS only (grey cloud)
  ```
- **If SPF exists** → Edit it, add `include:smtp2go.com` before `~all`:
  ```
  v=spf1 include:smtp2go.com [existing entries] ~all
  ```

### DKIM Record (from SMTP2GO dashboard)

SMTP2GO gives you a record like:
```
Type:  CNAME  (or TXT)
Name:  smtp2go._domainkey
Value: (unique string from SMTP2GO)
```
Add it exactly as shown. Set **Proxy: DNS only (grey cloud)** for CNAME.

> DNS changes on Cloudflare propagate in seconds to minutes (not hours).

---

## Step 3 — Cloudflare Turnstile (3 min)

1. **Cloudflare Dashboard → Turnstile → Add Site**
   - Label: `ryanoccg.com`
   - Domain: `ryanoccg.com`
   - Widget mode: **Managed** (shows checkbox) or **Invisible**
2. Copy the **Site Key** → paste into `src/index.html`:
   ```html
   <div class="cf-turnstile"
     data-sitekey="PASTE_SITE_KEY_HERE"
     data-theme="dark"
     data-size="flexible">
   ```
3. Copy the **Secret Key** → used in Step 4

---

## Step 4 — Deploy Cloudflare Worker

### Option A: Cloudflare Dashboard (no CLI, fastest)

1. **Cloudflare Dashboard → Workers & Pages → Create**
2. **Create Worker** → name it `ryanoccg-quote-worker` → **Deploy**
3. Click **Edit code** → select all → paste entire contents of `worker.js` → **Save & Deploy**
4. **Settings → Variables and Secrets**:

   | Type | Name | Value |
   |------|------|-------|
   | Secret | `SMTP2GO_API_KEY` | your `api-...` key |
   | Secret | `TURNSTILE_SECRET` | Turnstile secret key |
   | Variable | `SMTP2GO_SENDER` | `noreply@ryanoccg.com` |
   | Variable | `NOTIFY_TO` | `ryanoccg@gmail.com` |

5. For rate limiting KV:
   - **Workers & Pages → KV → Create namespace** → name: `RATE_LIMIT`
   - Back in your worker → **Settings → Bindings → Add → KV Namespace**
   - Variable name: `RATE_LIMIT`, select the namespace

### Option B: Wrangler CLI

```bash
cd projects/ryanoccg/worker
npm install

# Login
wrangler login

# Create KV for rate limiting
wrangler kv namespace create RATE_LIMIT
# → copy the id, paste into wrangler.toml (uncomment the [[kv_namespaces]] block)

# Set secrets
wrangler secret put SMTP2GO_API_KEY
wrangler secret put TURNSTILE_SECRET

# Deploy
wrangler deploy
```

---

## Step 5 — Custom Domain api.ryanoccg.com (2 min)

Since your DNS is on Cloudflare, this is automatic:

1. In your Worker → **Settings → Domains & Routes → Add Custom Domain**
2. Enter: `api.ryanoccg.com`
3. Click **Add Domain** — Cloudflare adds the DNS record automatically ✅

No manual DNS entry needed.

---

## Step 6 — Update Frontend

In `src/main.js`, update `WORKER_URL`:

```js
// Change this line:
const WORKER_URL = 'https://api.ryanoccg.com/quote';
```

Rebuild and upload `dist/` to cPanel:
```bash
cd projects/ryanoccg
npm run build
# Upload dist/ contents to public_html via cPanel File Manager
```

---

## Step 7 — Verify End-to-End

1. Open https://ryanoccg.com → scroll to **Request a Free Quote**
2. Fill in the form and submit
3. Check `ryanoccg@gmail.com` inbox (also check spam)
4. Check **Worker → Logs → Real-time** in Cloudflare dashboard for any errors

---

## Local Testing (Optional)

```bash
cd projects/ryanoccg/worker

# Fill in real values
cp .dev.vars.example .dev.vars
# edit .dev.vars

# Start worker on localhost:8787
npm run dev
```

Temporarily in `src/main.js`:
```js
const WORKER_URL = 'http://127.0.0.1:8787';
```

---

## Full Checklist

- [ ] SMTP2GO account + API key
- [ ] SPF record added in Cloudflare DNS
- [ ] DKIM record added in Cloudflare DNS
- [ ] Turnstile Site Key pasted in `index.html`
- [ ] Worker deployed with all 4 variables/secrets set
- [ ] KV namespace created + bound as `RATE_LIMIT`
- [ ] Custom domain `api.ryanoccg.com` added to Worker
- [ ] `WORKER_URL` updated in `main.js`
- [ ] Site rebuilt + uploaded to cPanel
- [ ] End-to-end test passed

---

## Cost Summary

| Service | Free Tier | Cost |
|---------|-----------|------|
| Cloudflare Worker | 100,000 req/day | Free |
| Cloudflare KV | 100,000 reads/day | Free |
| Cloudflare Turnstile | Unlimited | Free |
| SMTP2GO | 1,000 emails/month | Free |
| **Total** | | **RM 0** |
