# WhatsMine — Web Installer

Browser-based setup wizard for fresh deployments. A buyer uploads the files, opens the site, and is walked through requirement checks, license verification, database configuration, migrations, admin creation, and finalization — without touching a terminal.

The installer runs only until installation completes. Once finished it sets `APP_INSTALLED=true` in `.env` and locks itself; every `/install/*` route then redirects to the dashboard.

---

## 1. Stack context

- **Framework:** Laravel 12 (PHP 8.2+)
- **Frontend:** Inertia + Vite (installer screens are plain Blade so they work before assets/DB exist)
- **Realtime:** Laravel Reverb (WebSockets)
- **Queue/Cache/Session:** database drivers by default (no Redis required to install)
- **Payments:** Stripe / PayPal / Paddle / Razorpay / Cashfree / Tap (configured post-install, not in the wizard)

Two env flags drive the installer lifecycle:

| Flag | Meaning |
|------|---------|
| `APP_INSTALLED` | `false`/absent → wizard active. `true` → wizard locked. |
| `APP_DEMO_MODE` | When `true`, destructive actions and credential edits are disabled. |

---

## 2. Server requirements

Checked automatically on Step 1. Install is blocked until all **required** rows pass.

**PHP**
- PHP `>= 8.2`
- Extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `gd` (or `imagick`), `zip`, `intl`, `fileinfo`
- `exec()` allowed (used to run migrations/queue setup) — optional but recommended

**Writable paths** (0755, web-server-owned)
- `.env`
- `storage/` (recursive)
- `bootstrap/cache/`
- `public/` (for the storage symlink + uploads)

**Database:** MySQL 8.0+ / MariaDB 10.6+ reachable with a user that can `CREATE`, `ALTER`, `DROP`.

**Recommended:** `proc_open` enabled, `max_execution_time >= 120`, `memory_limit >= 256M`, SSL certificate (WhatsApp Cloud API webhooks require HTTPS).

---

## 3. Wizard flow

```
/install
  ├─ Step 1  Requirements   GET  /install/requirements
  ├─ Step 2  Permissions    GET  /install/permissions
  ├─ Step 3  License        GET/POST /install/license
  ├─ Step 4  Database       GET/POST /install/database
  ├─ Step 5  Environment    GET/POST /install/environment
  ├─ Step 6  Migrate + Seed POST /install/migrate
  ├─ Step 7  Admin account  GET/POST /install/admin
  └─ Step 8  Finish         GET  /install/finish
```

Each step stores its result in the session; a step is reachable only if the prior step passed. The wizard progress is a linear stepper with a "Next" button that is disabled while any required check fails.

---

## 4. Step-by-step

### Step 1 — Requirements
Runs the PHP version + extension + config checks from §2. Renders a green/red table. **Next** enabled only when every required row is green.

### Step 2 — Permissions
Attempts an actual write to each path in §2 (creates and deletes a temp file). Shows the failing path and a `chmod` hint on failure.

### Step 3 — License
- Field: **Purchase / Envato code**.
- POST validates the code against the license endpoint (CodeCanyon purchase-code verify). On success, stores the returned license token in the session and writes it to `.env` as the app license key at finalize.
- On failure: show the API error verbatim, do not advance.
- If the product ships without remote licensing, this step is a format-only check (UUID pattern) and can be skipped via config.

### Step 4 — Database
- Fields: `DB_HOST`, `DB_PORT` (3306), `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- POST opens a live PDO connection with the supplied values **before** saving anything. On connect failure, show the raw PDO message.
- On success, values are held in session (written to `.env` in Step 5), and the app key is generated if missing (`php artisan key:generate`).

### Step 5 — Environment
Writes the working `.env`. Collected fields:
- `APP_NAME`, `APP_URL` (must match the browser origin — used for webhooks, Reverb, OAuth redirects).
- Mail: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` (optional — can be filled later in admin settings; a "Skip mail" toggle is provided).
- Sets safe defaults: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `BROADCAST_CONNECTION=reverb`.
- Auto-generates `REVERB_APP_ID/KEY/SECRET` and `VAPID_PUBLIC_KEY/PRIVATE_KEY` (web push) so realtime + notifications work out of the box.

### Step 6 — Migrate + Seed
- Runs `php artisan migrate --force`.
- Runs the base seeders (roles/permissions, plans, settings, default admin placeholder, demo tenant if `APP_DEMO_MODE=true`).
- Runs `php artisan storage:link`.
- Long-running: the screen polls a status endpoint and streams progress; on any migration error the raw output is shown and the step can be retried (migrations are idempotent with `--force`).

### Step 7 — Admin account
- Fields: **Name**, **Email**, **Password** (confirmed).
- Creates the super-admin user and assigns the admin role. Overrides the `ADMIN_SEED_*` placeholder if one was seeded.
- In demo mode this step is skipped (demo admin already seeded).

### Step 8 — Finish
- Writes `APP_INSTALLED=true` to `.env`.
- Runs `php artisan config:cache route:cache event:cache` and `optimize`.
- Clears the installer session.
- Shows the post-install checklist (§6) and a "Go to login" button.

---

## 5. Route protection

Two guards keep the two worlds separate.

**`RedirectIfInstalled` middleware** — on every `/install/*` route:
```php
if (config('app.installed')) {
    return redirect()->route('login');
}
```

**`EnsureInstalled` middleware** — on the whole app (web + api groups):
```php
if (! config('app.installed') && ! $request->is('install*')) {
    return redirect()->route('install.requirements');
}
```

`config('app.installed')` reads `APP_INSTALLED` from `.env`. Register both in `bootstrap/app.php`.

---

## 6. Post-install checklist (shown on Finish)

These are **not** part of the wizard — they are configured in the admin panel or on the server after login:

1. **Queue worker** — `php artisan queue:work` (or Supervisor). Broadcasts, campaigns, AI generation, and webhooks are queued.
2. **Reverb** — `php artisan reverb:start` behind the same domain (WSS). Powers the live inbox.
3. **Scheduler** — cron `* * * * * php artisan schedule:run` for campaigns, drip automations, billing renewals.
4. **WhatsApp Cloud API** — add System User token + WABA ID in admin → integrations. Webhook URL is `APP_URL/api/webhooks/whatsapp`.
5. **Payments** — enable one of Stripe/PayPal/Paddle/Razorpay/Cashfree/Tap and paste keys + webhook secret.
6. **AI** — set `AI_PROVIDER`, `OPENAI_API_KEY`, and (optional) `QDRANT_URL` for the knowledge base.
7. **OAuth** — Google/Microsoft client IDs for social login + calendar/email integrations.

---

## 7. `.env` keys the wizard writes

| Step | Keys written |
|------|--------------|
| 3 License | app license token |
| 4 Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `APP_KEY` |
| 5 Environment | `APP_NAME`, `APP_URL`, `APP_ENV`, `APP_DEBUG`, `MAIL_*`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE`, `BROADCAST_CONNECTION`, `REVERB_*`, `VAPID_*` |
| 8 Finish | `APP_INSTALLED=true` |

Everything else (payment gateways, AI, push providers, OAuth, S3) stays blank and is filled from the admin settings UI post-install.

---

## 8. CLI fallback

For developers who skip the browser wizard:

```bash
cp .env.example .env
php artisan key:generate
# edit DB_* in .env
php artisan migrate --seed --force
php artisan storage:link
# set APP_INSTALLED=true in .env
php artisan optimize
```

Default seeded credentials come from `.env` (`ADMIN_SEED_EMAIL` / `ADMIN_SEED_PASSWORD`). Change them immediately.

---

## 9. Re-running / reset

To force the wizard again on an existing install: set `APP_INSTALLED=false` in `.env` and run `php artisan config:clear`. The wizard will re-run migrations idempotently; existing data is preserved unless you drop the database manually.
