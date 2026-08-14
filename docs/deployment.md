# Deployment

## Target

Linux, PHP 8.3+ (FPM), MySQL 8, Redis, Nginx. The kit runs on Windows for development, but Windows is not a supported production target — `ext-pcntl` is unavailable there, which rules out Horizon and makes queue workers less robust.

## Build

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan db:seed --force          # creates the super admin; safe to re-run
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Never cache config in an environment where `.env` is edited in place — clear and re-cache on every deploy instead.

## Environment

Beyond the standard Laravel keys:

| Key | Notes |
|---|---|
| `APP_DEBUG` | **must** be `false` |
| `APP_URL` | drives signed URLs and mail links; must match the public origin |
| `REDIS_CLIENT` | `predis` by default; switch to `phpredis` if the extension is installed — it is measurably faster |
| `BROADCAST_CONNECTION` | `pusher` once `PUSHER_*` are set; leave as `log` and realtime silently no-ops |
| `SCOUT_DRIVER` | `database` is fine to a few hundred thousand rows; move to `meilisearch` beyond that |
| `SAAS_ADMIN_EMAIL` / `SAAS_ADMIN_PASSWORD` | set **before** the first seed |
| `SAAS_SEED_DEMO_DATA` | ignored outside `local`, but set it `false` anyway |
| `TELESCOPE_ENABLED` | `false` in production unless you have gated the dashboard |

## Queue

```
php artisan queue:work redis --queue=high,default,low --tries=3 --max-time=3600
```

Run it under supervisord (or systemd) with `autorestart=true` and at least two workers. `--max-time` recycles the process hourly so a leak cannot accumulate.

To add the Horizon dashboard on Linux:

```bash
composer require laravel/horizon
php artisan horizon:install
```

## Scheduler

```
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
```

`routes/console.php` already schedules queue pruning, notification and audit-log retention, Sanctum token pruning, Telescope pruning and backups. Everything is `withoutOverlapping()` and `onOneServer()`, so scaling horizontally does not duplicate maintenance work.

## Nginx

Standard Laravel `public/` root, plus a Content-Security-Policy. CSP is set here rather than in middleware because it needs a per-response nonce and differs per deployment:

```nginx
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'nonce-$request_id'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' https://fonts.bunny.net; connect-src 'self' wss://*.pusher.com https://*.pusher.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'" always;
```

The application already sets `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy` and HSTS via `SecureHeaders` middleware.

## Storage

`FILESYSTEM_DISK=s3` covers both AWS S3 and Cloudflare R2. For R2 set `AWS_ENDPOINT` to the R2 endpoint, `AWS_DEFAULT_REGION=auto`, and `AWS_USE_PATH_STYLE_ENDPOINT=false`.

Media conversions are queued, so a worker must be running or thumbnails will never generate.

## Backups

`spatie/laravel-backup` is configured and scheduled. Point `backup.destination.disks` at an **off-site** disk — a backup written to the same volume as the database is not a backup. Verify a restore before you rely on it.

## Before you go live

Two defaults are deliberately safe and **must be changed on purpose**:

- **`SAAS_SEO_INDEXABLE=false`** — `robots.txt` disallows everything until you flip this, so a staging deploy is never indexed by accident. `php artisan seo:sitemap` will warn you that it is still disallowing.
- **`SAAS_BILLING_GATEWAY=manual`** — subscriptions and invoices are recorded locally and no processor is contacted. Set it to `stripe` and supply `STRIPE_*` before taking real money.

Also review `SAAS_API_LOG_BODIES` (default `false`) — request/response bodies can contain personal data.

## Post-deploy smoke check

1. `GET /up` returns 200.
2. Log in as the super admin; the dashboard renders.
3. Create a second workspace and switch to it; data from the first is not visible.
4. Trigger a queued job (export a user list) and confirm it completes.
5. `php artisan schedule:list` shows the expected tasks — including `billing:renew`, `blog:publish-scheduled`, `seo:sitemap`, `notifications:prune`, `audit:prune`.
6. `php artisan seo:sitemap` writes `public/sitemap.xml`.
7. `GET /api/v1/openapi.json` returns the spec; a Sanctum token authenticates against the public API and cannot read another workspace.
8. Load the site with devtools open — no console errors, no requests blocked by CSP.

**Media conversions and webhook deliveries are queued** — a worker must be running or thumbnails never generate and webhooks never fire.
