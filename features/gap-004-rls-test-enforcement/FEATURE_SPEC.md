# FEATURE SPEC — GAP-004: RLS Test Enforcement

## Goal

Ensure that every `TenantManager::setTenantContext()` call also sets `app.current_tenant_id` on the `pgsql-rls` connection, so existing isolation tests automatically exercise real PostgreSQL RLS without code changes.

## Problem

`TenantManager::setTenantContext()` only sets the PG variable on the default `pgsql` connection (user `sail`, `BYPASSRLS=true`). The `pgsql-rls` connection (`app_user`, `NOBYPASSRLS`) is never synced. Three RLS test files each duplicate the PG `set_config` call with private `setRlsContext()` helpers — a maintenance burden and a sign that the architecture is incomplete.

## Solution

### 1. Extend `TenantManager::setTenantContext()` and `clearTenantContext()`

Add a conditional sync to the `pgsql-rls` connection when it's configured:

```php
public function setTenantContext(string $tenantId): void
{
    // ... uuid validation ...
    DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

    if (config('database.connections.pgsql-rls')) {
        DB::connection('pgsql-rls')->statement(
            "SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]
        );
    }
    $this->currentTenantId = $tenantId;
}
```

Production impact: zero — `pgsql-rls` connection is only configured in `phpunit.xml` and `.env`.

### 2. Remove duplicated helpers from 3 RLS test files

| File | Remove |
|---|---|
| `tests/Feature/Security/RlsCrossTenantTest.php` | `setRlsContext()`, `clearRlsContext()` |
| `tests/Feature/Security/TenantModuleRlsTest.php` | `setRlsContext()`, `clearRlsContext()` |
| `tests/Feature/Security/SuperadminContextRlsTest.php` | `setRlsContext()`, `clearRlsContext()` |

Replace with `$this->tenantManager->setTenantContext()` / `$this->tenantManager->clearTenantContext()`.

### 3. Add test for dual-connection sync

1 test in an existing RLS file (or `SuperadminContextRlsTest`) that:
- Sets context via `TenantManager`
- Reads `current_tenant_id()` from `pgsql-rls` connection
- Asserts both connections return the same tenant UUID

## Migration

**None.**

## Tests (1 new + 0 regressions)

| # | Test | Connection |
|---|---|---|
| 1 | `test_set_context_syncs_both_connections` | `pgsql-rls` |

Existing tests in the 3 RLS files still pass because `setRlsContext()` is replaced by functionally identical `TenantManager` calls.

## Files affected

| File | Change |
|---|---|
| `app/Services/TenantManager.php` | `setTenantContext()` + `clearTenantContext()` sync `pgsql-rls` |
| `tests/Feature/Security/RlsCrossTenantTest.php` | Remove `setRlsContext/clearRlsContext`, use `TenantManager` |
| `tests/Feature/Security/TenantModuleRlsTest.php` | Same |
| `tests/Feature/Security/SuperadminContextRlsTest.php` | Same + add sync test |
| `docs/security/SECURITY_GAPS.md` | GAP-004 → fix aplicado |
| `engram.json` | v1.7.0, GAP-004 → fixed |

## DoD

- [x] `TenantManager` synced dual connection (`setTenantContext` + `clearTenantContext`)
- [x] 3 RLS test files cleaned (no `setRlsContext` duplicates — `TenantModuleRlsTest`, `SuperadminContextRlsTest`)
- [x] Sync test added (`test_set_context_syncs_both_connections` in `SuperadminContextRlsTest`)
- [x] Full suite green: 235 tests, 235 passed, 612 assertions
- [x] Pint run
- [x] `SECURITY_GAPS.md` + `engram.json` updated

### Desviaciones del plan original
- `RlsCrossTenantTest` no tenía helpers duplicados (ya usaba `TenantManager` directamente). Solo se limpiaron 2 de los 3 archivos.
