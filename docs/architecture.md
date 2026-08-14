# Architecture

## Why feature modules

A conventional Laravel app puts every model in `app/Models`, every controller in `app/Http/Controllers`, and every policy in `app/Policies`. That is fine at ten features and unworkable at eighty: the cost of understanding one feature grows with the size of every unrelated folder, and lifting a feature into another product means archaeology.

This kit organises by **feature vertical** instead. Everything the `User` feature needs lives under `app/Modules/User`. Removing it is deleting a folder; copying it into another product is copying a folder.

```
app/Modules/User/
  Models/                 Actions/          Services/        DTOs/
  Http/Controllers/       Http/Requests/    Http/Resources/  Http/Middleware/
  Policies/               Events/           Listeners/       Notifications/
  Enums/                  Exports/
  Database/Migrations/    Database/Seeders/
  Routes/web.php          Routes/api.php
  UserServiceProvider.php
```

Discovery is convention-based. `App\Providers\ModuleRegistryServiceProvider` scans `app/Modules`, finds `{Name}/{Name}ServiceProvider.php`, and registers it. `App\Support\Modules\ModuleServiceProvider` (the base class) then loads that module's routes, migrations, translations and policies. A module author writes a provider that is usually under thirty lines.

There is no `nwidart/laravel-modules`. The gain would be scaffolding commands and enable/disable toggles; the cost is a dependency, per-module `composer.json` files, and friction with IDEs and static analysis. PSR-4 namespaces already do the job.

## Layers

```
HTTP request
   │
   ├── FormRequest        validation + authorize() delegating to a Policy
   │
   ├── Controller         thin: resolve, delegate, respond
   │
   ├── Action             one use case, one public handle(), takes a DTO
   │   or Service         orchestration across several actions or external I/O
   │
   ├── Model              Eloquent, with the tenant scope applied
   │
   └── Resource           the exact JSON shape the frontend's TypeScript expects
```

Rules that hold everywhere:

- A controller method should read as a paragraph, not a page. If it contains business logic, that logic belongs in an Action.
- Actions receive **DTOs**, never arrays. `App\Support\DTOs\Data` is the base; see `App\Modules\Company\DTOs\CompanyData`.
- Anything that mutates security-relevant state calls `App\Modules\Audit\Services\SecurityLogger`.
- **Repositories are used only where the data source is genuinely swappable** (payment gateways, AI providers, storage). Wrapping Eloquent in a repository elsewhere buys indirection and no testability that a factory does not already give.

## The shared kernel — `app/Support`

Not a module. Consumed by all of them.

| Namespace | Purpose |
|---|---|
| `Support\Modules` | `ModuleServiceProvider`, the base every module extends |
| `Support\Tenancy` | `CurrentCompany` (the active workspace), `CompanyScope` (the global query scope) |
| `Support\Concerns` | `BelongsToCompany` — the one trait that makes a model tenant-owned |
| `Support\DataTable` | `TableBuilder`, `Column`, `Filter` — the server side of every index screen |
| `Support\Settings` | `SettingsRepository` — layered, cached, encryption-aware settings |
| `Support\Navigation` | `NavigationBuilder`, `NavigationSection`, `NavigationItem` — the sidebar, assembled by modules |
| `Support\DTOs` | `Data`, the readonly DTO base |
| `Support\Enums` | Cross-cutting enums and the `HasLabel` presentation trait |

## Multi-tenancy

**Single database, `company_id` column, global scope.** Chosen over database-per-tenant because it gives one migration path, makes cross-tenant admin reporting trivial, and is what comparable commercial kits ship. The trade-off — a bug in scoping leaks data across tenants — is mitigated by making the scope automatic and the bypass explicit.

Request flow:

1. `SetCurrentCompany` middleware reads the workspace id from the session (falling back to `users.current_company_id`, then the user's first membership).
2. It **re-verifies membership against the database on every request**. Revoking someone takes effect on their next click, not their next login.
3. It binds the resolved `Company` into the `CurrentCompany` singleton.
4. `CompanyScope` constrains every query on a model using `BelongsToCompany`.

Crossing the boundary is always visible in the code:

```php
Invoice::withoutCompanyScope()->get();          // all tenants
Invoice::forCompany($otherId)->get();           // one specific tenant
app(CurrentCompany::class)->bypass(fn () => …); // a whole block, un-scoped
```

`User` is deliberately **not** `BelongsToCompany`: a person can belong to several workspaces. User queries scope through the `company_user` pivot instead.

## Permissions

`config/permissions.php` is the single source of truth: permission groups, permission names, and the roles seeded from them. `php artisan permission:sync` reconciles the database with that file.

- Naming is `{group}.{action}` — `users.suspend`, `companies.members.invite`.
- A permission that is not declared there does not exist, and a check against it denies.
- `super-admin` is handled by a `Gate::before` in `App\Providers\AuthServiceProvider`, so it holds no permission rows.
- The sidebar is generated from these permissions, so a user is never shown a link to a 403.

The frontend receives a flat permission list in shared props and gates rendering with `usePermissions()`. **That is a convenience, not a boundary** — every route it points at is independently authorised by a Policy or `permission:` middleware.

## Frontend

- **Inertia** is the transport. Pages are React components under `resources/js/pages` whose paths mirror the names passed to `Inertia::render`.
- **TypeScript strict**, with `resources/js/types/index.d.ts` mirroring `HandleInertiaRequests::share()`. Changing a shared prop on the PHP side without changing that file is a build error.
- **Zustand** for global client state (sidebar, command palette, theme). **TanStack Query** only for data Inertia does not deliver — polling widgets, palette search. **Context** only for theme and identity.
- **Design tokens** live in `resources/css/app.css`, authored in OKLCH so light and dark stay perceptually matched. Components consume semantic tokens (`bg-card`, `text-muted-foreground`) and never a raw colour, which makes rebranding a single-file change.
- Code splitting is per route via Inertia's lazy page resolution, plus long-lived vendor chunks configured in `vite.config.ts`.

## Caching

| Key | TTL source | Busted by |
|---|---|---|
| `user:{id}:permissions` | `saas.cache.permissions_ttl` | user save/delete, role and permission changes |
| `navigation:{id}:{locale}` | `saas.cache.navigation_ttl` | permission changes, workspace switch |
| `settings:{scope}:{id}` | `saas.cache.settings_ttl` | any settings write |
| dashboard widgets | `saas.cache.dashboard_ttl` | time only |

## Security posture

- CSRF on every state-changing web route; Sanctum tokens for the API.
- `SecureHeaders` middleware sets `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, and HSTS over TLS. CSP is deliberately left to the web server, where a nonce can be issued.
- Rate limiters per surface (`web`, `api`, `auth`, `search`, `export`) with limits in `config/saas.php`. The `auth` limiter keys on credential **and** IP, so neither a shared NAT nor a rotating address pool defeats it.
- Destructive and credential-changing routes sit behind `password.confirm`.
- Secrets in settings are encrypted at rest and are never serialised to the client — resources send a masked placeholder and an `is_set` boolean.
- Every security-relevant mutation writes a `SecurityLog` row through a closed enum of event types.
