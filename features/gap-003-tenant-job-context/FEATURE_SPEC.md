# FEATURE SPEC — GAP-003: Tenant Context for Queue Jobs

## Goal

Ensure that every queued job has tenant context (`app.current_tenant_id` set in the PostgreSQL session) when it runs, so that RLS policies and the `BelongsToTenant` global scope work correctly outside the HTTP request lifecycle.

## Problem

Currently, **zero Job classes exist** in the codebase, but the queue infrastructure is configured (`QUEUE_CONNECTION=database`) and the dev script runs `queue:listen`. When jobs are introduced:

1. A job dispatched during an HTTP request has PHP `TenantManager` context in memory.
2. When the queue worker processes the job (different process, potentially different server), the in-memory context is **lost**.
3. `BelongsToTenant` silently no-ops (returns unfiltered).
4. RLS on raw queries explodes with `tenant_context_missing`.

This is exactly GAP-002's pattern but for async contexts.

## Solution

### 1. Queue middleware: `SetTenantContextForJob`

A Laravel job middleware that reads `tenant_id` from the job instance and sets `app.current_tenant_id` in the PostgreSQL session before the job's `handle()` runs.

```php
// app/Jobs/Middleware/SetTenantContextForJob.php
class SetTenantContextForJob
{
    public function handle(Job $job, callable $next): void
    {
        if (isset($job->tenantId) && $job->tenantId !== null) {
            app(TenantManager::class)->setTenantContext($job->tenantId);
        }

        $next($job);
    }
}
```

### 2. Trait: `BelongsToTenantJob`

A trait for job classes that auto-captures tenant context at dispatch time and registers the middleware.

```php
// app/Models/Concerns/BelongsToTenantJob.php
trait BelongsToTenantJob
{
    public ?string $tenantId = null;

    public function initializeBelongsToTenantJob(): void
    {
        $manager = app(TenantManager::class);
        if ($manager->hasContext()) {
            $this->tenantId = $manager->getCurrentTenantId();
        }
    }

    public function middleware(): array
    {
        return [app(SetTenantContextForJob::class)];
    }
}
```

### 3. Test Doubles directory

Create `tests/Doubles/Jobs/` for test-only job classes that prove the infrastructure works.

### 4. Clear `afterCreate` context note

Ensure `CreateTenant::afterCreate()` works with the job pattern (already done in GAP-002 — no change needed).

## Migration

**None.** No new database tables. The `jobs`, `job_batches`, and `failed_jobs` tables already exist.

## Tests (4 total)

### `TenantJobContextAppScopeTest.php` (4 tests, connection `pgsql`)

| # | Test | Assertion |
|---|---|---|
| 1 | `trait_captures_tenant_id_at_dispatch` | job dispatched inside tenant context has `tenantId` set |
| 2 | `trait_leaves_tenant_id_null_without_context` | job dispatched outside tenant context has `tenantId = null` |
| 3 | `middleware_sets_context_before_handle` | job with `tenantId` runs with `TenantManager::hasContext() = true` |
| 4 | `middleware_skips_context_when_null` | job with `tenantId = null` runs without setting context |

## Files affected

| File | Change |
|---|---|
| `app/Jobs/Middleware/SetTenantContextForJob.php` | New — queue middleware |
| `app/Models/Concerns/BelongsToTenantJob.php` | New — trait for jobs |
| `tests/Doubles/Jobs/WithTenantContextJob.php` | New — test job (uses trait, has handle) |
| `tests/Feature/Security/TenantJobContextAppScopeTest.php` | New — 4 tests |
| `docs/security/SECURITY_GAPS.md` | Update GAP-003 → fix aplicado |

## DoD

- [x] FEATURE_SPEC.md approved (GATE 0)
- [x] Queue middleware created (`SetTenantContextForJob`)
- [x] Trait created (`BelongsToTenantJob`)
- [x] Test doubles created (`WithTenantContextJob` en tests/Doubles/Jobs/)
- [x] Tests written first (TDD): 4 tests, 0 initial
- [x] Suite green: `vendor/bin/sail artisan test --compact --filter=TenantJobContext` → 4/4
- [x] Full suite green: 234 tests, 234 passed, 610 assertions
- [x] Pint: `vendor/bin/sail bin pint --format agent`
- [x] `engram.json` updated: GAP-003 → fixed, v1.6.0
- [x] `SECURITY_GAPS.md` updated

### Desviaciones del plan original
- Se eliminó el test 5 (restore previous context) porque en queue worker cada job debe empezar limpio. El middleware siempre hace `clearTenantContext()` en `finally`.
- `WithoutTenantContextJob` no se creó porque `WithTenantContextJob` cubre ambos casos (tenantId seteado y null).
