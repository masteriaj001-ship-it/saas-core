# FEATURE SPEC — GAP-002: Superadmin Tenant Context

## Goal

Fix GAP-002: when a superadmin enters a tenant-specific panel (`/admin/{tenant:slug}`), `app.current_tenant_id` is never set — RLS explodes on raw queries, and the `BelongsToTenant` global scope silently returns unfiltered data.

## Problem

```php
// SetTenantContext.php:23
if ($user->is_superadmin) {
    return $next($request);  // skips context entirely
}
```

Consequences:
- Raw SQL / `DB::table()` → `current_tenant_id()` raises `tenant_context_missing`.
- `BelongsToTenant` global scope → silently no-ops → superadmin sees ALL rows.
- Tenant creation in `CreateTenant.php` sets context but never clears it — leaks to subsequent requests.

## Solution

### 1. Modify `SetTenantContext` middleware

When the user is superadmin AND the request has a resolved tenant (Filament panel), set context to that tenant's ID. If no tenant is resolvable (superadmin panel, API, etc.), keep the current skip behavior.

### 2. Add `withoutTenantContext()` helper to `TenantManager`

Allows safe temporary context clearing for superadmin global operations:

```php
$manager->withoutTenantContext(fn () => Tenant::all());
```

Instead of the current fragile pattern of manual clear/set pairs.

### 3. Add `current_tenant_id_or_null()` PG function

Soft fallback for operations that should work without strict context. Returns `null` instead of raising an exception when `app.current_tenant_id` is not set.

### 4. Fix Tenant creation context leak

`CreateTenant.php` calls `setTenantContext()` inside `handleRecordCreation()` but never calls `clearTenantContext()`. Add cleanup after creation completes.

## Migration

1 migration:
- `create_current_tenant_id_or_null_function.php` → idempotent PG function

## Tests (8 total — +3 respecto al plan original)

### `SuperadminContextAppScopeTest.php` (5 tests, connection `pgsql`)

| # | Test | Assertion |
|---|---|---|
| 1 | `superadmin_sets_context_when_tenant_resolvable` | middleware con `resolveTenant()` mockeado → `TenantManager::hasContext() = true`, `getCurrentTenantId() = tenant->id` |
| 2 | `superadmin_skips_context_when_no_tenant_resolved` | middleware sin tenant → `hasContext() = false` (superadmin panel) |
| 3 | `without_tenant_context_clears_and_restores` | `callable` inside `withoutTenantContext()` has no context; after it returns, previous context is restored |
| 4 | `without_tenant_context_without_prior_context` | sin contexto previo → no hay contexto ni dentro ni fuera |
| 5 | `create_tenant_clears_context_after_creation` | After POST `/superadmin/tenants`, `TenantManager::hasContext() = false` |

### `SuperadminContextRlsTest.php` (3 tests, connection `pgsql-rls`)

| # | Test | Assertion |
|---|---|---|
| 1 | `current_tenant_id_or_null_returns_null_without_context` | sin contexto → `current_tenant_id_or_null()` retorna NULL |
| 2 | `current_tenant_id_or_null_returns_id_with_context` | con contexto → retorna UUID correcto |
| 3 | `current_tenant_id_still_throws_without_context` | `current_tenant_id()` sigue lanzando `tenant_context_missing` sin contexto (no se rompió nada) |

### Desviaciones del plan original
- Test 1 (superadmin context) no se puede probar via HTTP porque `Filament::getTenant()` no se resuelve en test context. Se usa anonymous class que extiende `SetTenantContext` y sobreescribe `resolveTenant()`.
- `SetTenantContext` cambió de `final` a `class` para permitir herencia en test. El `resolveTenant()` es `protected`.
- Se agregaron 2 tests extra (edge cases) que no estaban en el spec original: `superadmin_skips_context` y `without_tenant_context_without_prior_context`.

## Files affected

| File | Change |
|---|---|
| `app/Services/TenantManager.php` | Add `withoutTenantContext(callable)` method |
| `app/Http/Middleware/SetTenantContext.php` | Set context for superadmins when tenant is resolvable |
| `app/Filament/Superadmin/Resources/TenantResource/Pages/CreateTenant.php` | Call `clearTenantContext()` after successful creation |
| `database/migrations/..._create_current_tenant_id_or_null_function.php` | New PG function `current_tenant_id_or_null()` |
| `tests/Feature/Security/SuperadminContextAppScopeTest.php` | New (3 tests) |
| `tests/Feature/Security/SuperadminContextRlsTest.php` | New (2 tests) |

## DoD

- [x] FEATURE_SPEC.md approved (GATE 0)
- [x] SQL borrador → APROBADO → migration generated (GATE 2)
- [x] Tests written first (TDD): 8 tests, 4 pass (RLS/migration) + 1 fail + 2 errors (intencional)
- [x] Code implemented
- [x] Suite green: `vendor/bin/sail artisan test --compact --filter=SuperadminContext` → 8/8
- [x] Full suite green: 230 tests, 230 passed, 604 assertions
- [x] Pint: `vendor/bin/sail bin pint --format agent` → 2 files fixed
- [x] `engram.json` updated: v1.5.0, GAP-002/GAP-005 fixed, test stats, features list
- [x] `docs/security/SECURITY_GAPS.md` updated
