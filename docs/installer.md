# Web installer

Browser-based setup for fresh Hotel Management SaaS deployments. Buyers open the site and complete requirements, permissions, license, database, environment, migrations, and platform admin creation — without using a terminal.

Specification and product notes live in [`installer.md`](installer.md). This document is the operator-facing summary of what shipped.

## Open the wizard

1. Deploy the files and ensure `.env` is missing or `APP_INSTALLED=false` (and ideally no `APP_KEY` yet).
2. Visit `/install` (or any app URL — uninstalled sites redirect here).
3. Complete the stepper through **Finish**.
4. Sign in at `/admin/login`.

Once finished, `APP_INSTALLED=true` is written and `/install/*` redirects to the admin login.

## Reset

```bash
# .env
APP_INSTALLED=false
php artisan config:clear
```

Then revisit `/install`. Migrations are idempotent; drop the database manually if you need a clean schema.

## CLI fallback

```bash
cp .env.example .env
php artisan key:generate
# edit DB_* then:
php artisan migrate --seed --force
php artisan storage:link
# set APP_INSTALLED=true
php artisan optimize
```
