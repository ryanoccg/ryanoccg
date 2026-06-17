# Splitwell — Deployment Guide (cPanel)

Splitwell is parked inside the `ryanoccg/ryanoccg` repo under `split/` and served
at **`split.ryanoccg.com`** on the same cPanel host as the portfolio.

- **Frontend:** React + Vite + TS → static files in the subdomain docroot.
- **Backend:** plain PHP 8.1 REST API in `<docroot>/api`.
- **Database:** MySQL/MariaDB.
- **Secrets & uploads:** kept in `~/app_private/`, **outside** any web root.

CI builds + deploys automatically (`.github/workflows/deploy-split.yml`). The
steps below are the **one-time manual setup** CI cannot do.

---

## 1. Create the subdomain

cPanel → **Domains / Subdomains** → add `split` under `ryanoccg.com`.
Note the **Document Root** it assigns (e.g. `public_html/split`). DNS is on
Cloudflare — add an `A`/`CNAME` record for `split` pointing at the server (or
let cPanel's "proxy" record be replicated in Cloudflare). Confirm the subdomain
serves over HTTPS (AutoSSL / Cloudflare).

## 2. Create the database

cPanel → **MySQL Databases**:
1. Create a database, e.g. `cpaneluser_split`.
2. Create a user with a strong password and **add it to the database with all
   privileges**.
3. cPanel → **phpMyAdmin** → select the DB → **Import** → upload `split/db/schema.sql`.

## 3. Secrets & uploads (outside the web root)

Create `~/app_private/` (a sibling of `public_html`, never web-accessible):

```
~/app_private/
  secrets.php        # copy of split/api/secrets.example.php with real values
  uploads/           # receipt images (chmod 700)
```

Fill `secrets.php` (see `split/api/secrets.example.php`):
- `db` → the DB name/user/pass from step 2
- `jwt_secret` → 32+ random chars (`openssl rand -base64 48`)
- `openai_api_key` → OpenAI key (model `gpt-5-mini`)
- `stripe_secret_key`, `stripe_webhook_secret`, `stripe_price_pro`
- `app_url` → `https://split.ryanoccg.com`
- `uploads_dir` → absolute path to `~/app_private/uploads`
- `cors_origin` → `https://split.ryanoccg.com`
- `adsense_client_id`, `admin_secret`

> `api/config.php` finds secrets automatically at `~/app_private/secrets.php`.
> Confirm PHP 8.1 with `curl`, `pdo_mysql`, `gd`/`fileinfo` (cPanel → MultiPHP /
> Select PHP Version).

## 4. GitHub Actions secrets/vars

The deploy workflow reuses the portfolio SSH secrets and adds one:

| Kind | Name | Value |
|------|------|-------|
| Secret | `SSH_PRIVATE_KEY`, `SSH_HOST`, `SSH_PORT`, `SSH_USERNAME` | (already set for the portfolio) |
| Secret | `SPLIT_DOCROOT` | the subdomain docroot, e.g. `~/public_html/split` |
| Secret | `MIGRATE_PHP` | (optional) CLI PHP binary for migrations if `php` isn't 8.1+, e.g. `ea-php81` |
| Variable | `VITE_ADSENSE_CLIENT_ID` | public AdSense client id (optional) |

Push to `main` touching `split/**` → the app builds and deploys. Or run the
**Deploy Splitwell** workflow manually (workflow_dispatch).

### Database migrations (automatic)

Schema changes ship as `split/api/migrations/NNN_name.sql` files. After each
deploy, the workflow runs `php api/migrate.php` over SSH, which applies any new
migration files (tracked in a `schema_migrations` table) — so **you don't run
SQL by hand**. The runner is idempotent: already-applied or "already exists"
migrations are skipped. To add a change, drop a new numbered `.sql` in
`migrations/` and push. (You can also run `php api/migrate.php` on the server
manually any time.)

## 5. Stripe

1. Create a **Product → recurring Price** for Pro; put its `price_...` id in
   `stripe_price_pro`.
2. **Developers → Webhooks → Add endpoint**:
   `https://split.ryanoccg.com/api/stripe_webhook.php`
   events: `checkout.session.completed`, `customer.subscription.created`,
   `customer.subscription.updated`, `customer.subscription.deleted`.
   Copy the signing secret → `stripe_webhook_secret`.
3. Test with Stripe **test mode** keys first.

## 6. AdSense (Free plan ads)

Ads only render for Free users and only if `VITE_ADSENSE_CLIENT_ID` is set.
AdSense requires site approval + a published privacy/consent notice — the app
ships a `/privacy` page; review it before enabling ads.

## 7. Grant the hidden "Super" plan

Never shown in pricing, never purchasable. Either:
- phpMyAdmin: `UPDATE users SET plan='super' WHERE email='you@example.com';`, or
- `curl -X POST https://split.ryanoccg.com/api/admin_set_plan.php \
     -H "X-Admin-Secret: <admin_secret>" \
     -H "Content-Type: application/json" \
     -d '{"email":"you@example.com","plan":"super"}'`

---

## Local development

```bash
# Backend (PHP built-in server)
cp split/api/secrets.example.php split/api/secrets.php   # gitignored; point db at a local MySQL
php -S localhost:8000 -t split/api

# Frontend (Vite proxies /api → localhost:8000)
cd split/frontend
cp .env.example .env
npm install
npm run dev    # http://localhost:5173
```

Verify the settlement math anytime:
```bash
cd split/frontend
node -e "require('esbuild').build({entryPoints:['tools/settlement.check.ts'],bundle:true,outfile:'/tmp/s.cjs',platform:'node',format:'cjs'}).then(()=>require('/tmp/s.cjs'))"
```
