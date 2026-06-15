# Splitwell — Meal Expense Splitter

A small SaaS web app for splitting meal & receipt costs **unevenly** across a
group. Upload receipt photos → AI OCR extracts the line items → assign each item
to the people who shared it (personal / shared / weighted) → record who paid →
**compile the session** to see who pays whom, minimized.

Parked inside the `ryanoccg/ryanoccg` repo under `split/` and deployed to
**`split.ryanoccg.com`** on the same cPanel host. See **[DEPLOY.md](./DEPLOY.md)**.

## The core flow

1. **Log in / sign up.**
2. **Create a session** (a trip / outing) and add **members** (named people).
3. **Add receipts** — one session holds many receipts, each from a different
   place. Scan a photo (OpenAI `gpt-5-mini` OCR) or enter items by hand, then
   assign items and pick who paid.
4. **Compile & settle up** — net balances + the minimal "who pays out → who
   takes back" list, across all places in the session.

## Stack

| Layer | Tech |
|-------|------|
| Frontend | React + Vite + TypeScript (`frontend/`) |
| Backend | PHP 8.1 REST, PDO + prepared statements (`api/`) |
| Auth | Stateless JWT (HS256) |
| DB | MySQL/MariaDB (`db/schema.sql`) |
| OCR | OpenAI `gpt-5-mini`, structured outputs (server-side) |
| Billing | Stripe Checkout + webhook |
| Ads | Google AdSense (Free plan only) |

## Plans

| Plan | Sessions | OCR/mo | Members/session | Ads |
|------|----------|--------|------------------|-----|
| Free | 2 | 10 | 5 | yes |
| Pro | ∞ | 200 | ∞ | no |
| Super (hidden) | ∞ | ∞ | ∞ | no |

Limits are enforced server-side in `api/lib/limits.php`; `super` bypasses all
checks and is granted only via `admin_set_plan.php` / a DB update.

## Layout

```
split/
  frontend/   React + Vite + TS SPA  (settlement.ts holds the split math)
  api/        PHP REST endpoints      (bootstrap.php wires config/db/cors/auth/limits)
  db/         schema.sql
  DEPLOY.md   one-time cPanel setup
```

## Security notes

- Secrets (`secrets.php`) and receipt uploads live **outside the web root**
  (`~/app_private/`); both are gitignored and never deployed by CI.
- Every query is scoped to the authenticated user; receipt images are served
  only through the authenticated `api/image.php`.
