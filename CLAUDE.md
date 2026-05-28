# Jaosoft SaaS Architecture Guidelines

## Core Principles
- **Multi-industry Abstraction:** Never use domain-specific terms in backend code. Use abstract concepts: `Asset`, `Item`, `Contact`, `WorkOrder`, `Space`, `Transaction`.
- **Infrastructure Security:** Data isolation is enforced at PostgreSQL level via RLS driven by `app.current_tenant_id` (`set_config`).
- **Stack:** Laravel 13 · PHP 8.5 · PostgreSQL 18 · Filament 5 · Docker/Sail.

## Routing & Panels
- **Admin panel** (`/admin/{tenant:slug}`): multi-tenant with `->tenant(Tenant::class, slugAttribute: 'slug')`. All resource URLs include `{tenant}`. Use `XxxResource::getUrl('index')` not `route(...)`.
- **Superadmin panel** (`/superadmin`): global, no tenant context, no `SetTenantContext` middleware. Bypass global scopes with `withoutGlobalScope('tenant')`.
- **Middleware pipeline (admin)**: `SetTenantContext` → `VerifyTenantStatus` → rest. `VerifyTenantStatus` blocks if `tenant->is_active === false` (superadmins bypass).
- **Login** at `/admin/login`. Register at `/register`. Password reset via Blade views.

## Data Model
- **UUID PKs:** All entity tables use `uuid DEFAULT gen_random_uuid()`. Models must have `$incrementing = false` and `$keyType = 'string'` (required for Spatie eager loading).
- **Tenant isolation:** `tenant_id uuid NOT NULL` on all multi-tenant tables (nullable only on `users` for superadmins).
- **Soft delete:** `deleted_at timestamptz` on all TenantModel entities.
- **Global tables (no tenant_id):** `tenants`, `modules_catalog`.

## Eloquent & Traits
- **`BelongsToTenant`** trait (custom, not Filament's): adds global scope + auto-fills `tenant_id` on `creating` event. Throws `RuntimeException` if no context and `is_superadmin !== true`.
- **`TenantModel`** abstract base: `use BelongsToTenant, HasUuids, SoftDeletes`. All multi-tenant models extend this.
- **Relationships:** all models have `tenant(): BelongsTo`. User has `$user->tenant` (BelongsTo) and implements `Filament\Models\Contracts\HasTenants`.

## Filament 5 (Schema API)
- **`form(Schema $schema): Schema`** — NOT `Form`. Imports from `Filament\Schemas\Components`.
- **Actions moved:** `Filament\Tables\Actions\*` → `Filament\Actions\*` (EditAction, DeleteAction, CreateAction, BulkActionGroup).
- **Properties as methods:** `$navigationGroup` and `$navigationLabel` must be getter methods (not static properties) when using `__()` translation.
- **`match()` must have `default`** to avoid `UnhandledMatchError`.
- **`->hidden()` renders nothing** — use `->selectablePlaceholder(false)` for selects with single option.
- **`ConvertEmptyStringsToNull`** — numeric fields need `->required()` to prevent null on NOT NULL columns.

## Spatie Permission
- **No Spatie teams:** `config/permission.php` → `teams = false`, `store = array`.
- **Custom models:** `Role` and `Permission` extend Spatie + BelongsToTenant + HasUuids.
- **Cache:** `array` driver (per-request, not persistent).

## Commands
- `jaosoft:make-superadmin`: creates user with `tenant_id=null, is_superadmin=true`.
- `jaosoft:create-tenant-admin`: transactional — Tenant → setTenantContext → User (owner) → commit.
- `tenant:create`: legacy, same as create-tenant-admin but non-interactive.

## Testing
- Maintain **60/60** tests passing (165 assertions). 10 suites: Spatie (3), WorkOrders (2), Transactions (1), Auth (3), Onboarding (1).
- Test database uses `phpunit.xml` config — `sail` runs as SUPERUSER (RLS bypassed in dev; BelongsToTenant scope compensates).

## Prohibitions
- No Prisma. No `stancl/tenancy` or `spatie/laravel-multitenancy`. No `SELECT *`. No `Model::all()` without tenant scope. No new packages without approval. No `__()` in static properties.
