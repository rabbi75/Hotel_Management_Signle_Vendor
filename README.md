# Hotel Management

A production-grade, feature-modular foundation for building SaaS products on Laravel + Inertia + React.

It is not a demo. Every screen ships with validation, authorisation, loading, empty and error states, and tests. The intent is that a CRM, HRM, LMS, booking or project-management product is built *on top* of this without first re-solving authentication, tenancy, RBAC, settings, auditing and the admin UI.

---

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 13, PHP 8.3+ |
| Database | MySQL 8 |
| Cache / queue / session | Redis (via `predis`, so no PHP extension is required) |
| Frontend | React 19, TypeScript (strict), Inertia.js 2, Vite 7 |
| Styling | Tailwind CSS v4 with an OKLCH token system |
| UI primitives | Radix + shadcn-style components, vendored into the repo |
| Auth | Fortify (+ 2FA, passkeys), Sanctum for API tokens |
| Realtime | Pusher via Laravel Broadcasting + Echo |
| RBAC | spatie/laravel-permission |
| Media | spatie/laravel-medialibrary + intervention/image |
| Audit | spatie/laravel-activitylog + a dedicated security log |
| Exports | maatwebsite/excel |
| Quality | Pint, Larastan (level 6), Rector, Pest, Vitest, ESLint, Prettier |

---

## Requirements

- PHP **8.3+** with `gd`, `intl`, `bcmath`, `exif`, `zip`, `pdo_mysql`, `sodium`, `fileinfo`
- MySQL 8
- Redis
- Node 20.19+ / 22.13+
- Composer 2

> **Windows note:** `laravel/horizon` is intentionally *not* a dependency — it requires `ext-pcntl`, which does not exist on Windows. Add it on a Linux deployment (`composer require laravel/horizon`) if you want the queue dashboard. `php artisan queue:work` plus Telescope cover local development.

---

## Getting started

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# create the database named in DB_DATABASE, then:
php artisan migrate --seed
php artisan storage:link

composer dev     # serve + queue worker + log tail + vite, all in one
```

The seeder creates a super-admin. Override the credentials with `SAAS_ADMIN_EMAIL` / `SAAS_ADMIN_PASSWORD` before seeding; the defaults are `admin@example.com` / `password`, and an existing account is never overwritten.

Mail is delivered to Mailpit (`http://localhost:8025` under Laragon). Realtime features stay inert until `PUSHER_*` credentials are set and `BROADCAST_CONNECTION=pusher` — they degrade to no-ops rather than erroring.

---

## Layout

```
app/
  Modules/            feature verticals — the unit of reuse
    User/  Role/  Company/  Auth/  Settings/  Notification/  Audit/  Dashboard/  Search/
    Billing/  Media/  Chat/  CMS/  Blog/  SEO/  Api/  AI/
  Support/            the shared kernel every module builds on
    DataTable/  DTOs/  Enums/  Modules/  Navigation/  Settings/  Tenancy/  Concerns/
  Http/Middleware/    application-wide middleware
  Providers/
config/
  saas.php            every tunable in the kit
  permissions.php     the permission registry — the single source of truth
resources/js/
  components/ui/      vendored primitives (button, dialog, table, ...)
  components/         app-shell, data-table, forms, charts, command-palette
  layouts/            app, auth, settings
  pages/              Inertia pages, mirroring route names
  hooks/  stores/  types/  lib/
```

Each module owns its `Models`, `Http/{Controllers,Requests,Resources}`, `Actions`, `Services`, `DTOs`, `Policies`, `Events`, `Listeners`, `Notifications`, `Enums`, `Database/{Migrations,Seeders}` and `Routes`. A module is registered simply by existing: `ModuleRegistryServiceProvider` discovers `app/Modules/{Name}/{Name}ServiceProvider.php` and wires its routes, migrations, translations and policies.

See [docs/architecture.md](docs/architecture.md) and [docs/adding-a-module.md](docs/adding-a-module.md).

---

## Conventions

- **Controllers stay thin.** Validate in a FormRequest, authorise with a Policy, delegate to an Action (one use case) or a Service (orchestration), return an Inertia render or a Resource.
- **DTOs cross boundaries.** Actions take readonly DTOs, never loose arrays.
- **Repositories only where they earn their keep** — swappable data sources such as payment gateways or AI providers. Eloquent is the repository everywhere else.
- **No magic strings.** Fixed sets are enums; tunables live in `config/saas.php`; permissions live in `config/permissions.php`.
- **No raw colours in components.** Only the semantic tokens defined in `resources/css/app.css`.
- **Authorisation is server-side.** Frontend permission checks decide what to *render*; they are never the security boundary.

---

## Multi-tenancy

Single database, `company_id` foreign key, global scope.

- `App\Support\Concerns\BelongsToCompany` adds the relation, the global scope, and the create-time stamp.
- `SetCurrentCompany` middleware resolves the workspace from the session and re-verifies membership on **every** request, so revoking a member takes effect immediately rather than at their next login.
- Crossing the boundary is always explicit: `Model::withoutCompanyScope()`, `Model::forCompany($id)`, or `CurrentCompany::bypass()`.

---

## Quality gates

```bash
composer lint          # pint --test + phpstan (larastan level 6)
composer fix           # pint + rector
composer test          # pest
npm run types          # tsc --noEmit
npm run lint           # eslint
npm test               # vitest
```

All six must pass before a change ships.

---

## Modules

All seventeen ship in this release.

| Module | What it gives you |
|---|---|
| `User` | CRUD, suspend/restore, impersonation, profile, avatars, preferences, export |
| `Role` | RBAC on spatie/laravel-permission, permission matrix, `permission:sync` |
| `Company` | Workspaces, members, invitations, departments, teams, ownership transfer |
| `Auth` | Fortify + 2FA (TOTP + recovery codes), passkeys, sessions, devices, login history, social-login drivers |
| `Settings` | Layered system/company/user store, encrypted secrets, SMTP, storage, maintenance mode |
| `Notification` | Database + mail + broadcast, notification centre, realtime bell |
| `Audit` | Activity log, login history, security log, exports, retention pruning |
| `Dashboard` | Widget registry, drag-and-drop layout, live stats |
| `Search` | Provider-based global search behind the ⌘K palette |
| `Billing` | Gateway-agnostic contract, Stripe (Cashier) + Manual drivers, plans, coupons, invoices, usage limits, dunning |
| `Media` | Folder tree, drag-drop upload, image editor, conversions, S3 / Cloudflare R2 |
| `Chat` | Realtime conversations, typing, read receipts, presence, reactions, attachments |
| `CMS` | Pages, 10 block types, drag-drop block editor, menus, signed draft previews |
| `Blog` | Posts, categories, tags, scheduling, markdown/rich text, comment moderation, RSS |
| `SEO` | Per-model meta, JSON-LD, sitemap, robots, actionable SEO scoring |
| `Api` | Versioned public REST API, Sanctum tokens, webhooks with HMAC + retries, request logs, OpenAPI 3.1 |
| `AI` | Provider abstraction (Anthropic, OpenAI, Gemini, DeepSeek, Grok), prompt templates, credits, SSE streaming |

**Verified on this build:** pint · phpstan level 6 (zero errors) · **649 Pest tests** · tsc strict · eslint · prettier · 59 Vitest · production build · `migrate:fresh --seed` from clean · **37-route authenticated smoke pass**.

### Adding the next module

Everything above is the same shape. `app/Modules/{Name}/` with a `{Name}ServiceProvider` is discovered automatically — see [docs/adding-a-module.md](docs/adding-a-module.md).

---

## Licence

MIT.
