# Splitwell — Meal Expense Splitter

A small SaaS web app for splitting meal & receipt costs **unevenly** across a
group. Upload receipt photos → AI OCR extracts the line items → assign each item
to the people who shared it (personal / shared / weighted) → record who paid →
**compile the session** to see who pays whom, minimized.

Parked inside the `ryanoccg/ryanoccg` repo under `split/` and deployed to
**`split.ryanoccg.com`** on the same cPanel host. See **[DEPLOY.md](./DEPLOY.md)**.

## The core flow

1. **Log in / sign up.**
2. **Create a session** (a trip / outing) and add the **people** in it. People are
   **account-level contacts** (an address book): add someone once and search/reuse
   them in any session. Adding to a session links a contact (or quick-adds a new one).
3. **Add receipts** — one session holds many receipts, each from a different
   place. Scan a photo (OpenAI `gpt-5-mini` OCR) or enter items by hand, then
   assign each item to the people who shared it (with a search box when there are
   many) and pick who paid. You can quick-add a contact right on the review screen.
4. **Compile & settle up** — net balances + the minimal "who pays out → who
   takes back" list, across all places in the session. This is computed by
   deterministic code (`settlement.ts`), **not** the AI.

> **Contacts** are managed on a dedicated screen (rename, soft-delete). Deleting a
> contact hides it from the address book but keeps it in past sessions. The AI is
> used only to read receipt photos; all money math is plain code.

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
  only through the authenticated `api/image.php` (`nosniff`, image MIME only).
- Auth is a stateless HS256 JWT (no `alg`-from-token confusion). The docroot
  `.htaccess` passes the `Authorization` header to PHP (`CGIPassAuth` + a
  rewrite fallback) — required under cPanel FCGI.
- `image_path` is validated against our generated filename pattern on write;
  uploaded images are deleted from disk when a receipt/session is deleted or its
  photo replaced.
- Plan limits + monthly OCR usage are enforced server-side; the OCR scan is
  reserved before the OpenAI call (no check-then-use race, no free-retry drain).

### Known limitations (deferred, not blockers for v1)

- **No pre-auth rate limiting** on `login`/`register` — add a per-IP throttle
  (e.g. Cloudflare rules or an attempts table) before public launch.
- **`image.php` accepts a `?token=`** so `<img>` can load it; the JWT can appear
  in access logs. A signed, short-lived image URL would harden this.
- **Stripe events are processed without ordering guarantees** — a rare
  out-of-order `subscription.updated` could momentarily flip plan state.
- **Abandoned OCR uploads** (scanned but never saved) aren't reaped; add a cron
  to sweep unreferenced files in `uploads/`.
