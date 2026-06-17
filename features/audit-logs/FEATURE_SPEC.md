# FEATURE SPEC: Cross-Cutting Audit Logs

## ID
**AUDIT-LOGS-001**

## Goal
Add generalized audit logging (activity log) across all tenant models using `spatie/laravel-activitylog`, tenant-aware via `BelongsToTenant` and RLS.

## Motivation
- `work_order_activities` is a domain-specific audit log for Talleres. Generalizing it gives traceability to every model change across the system.
- Installing before Facturación avoids retrofitting audit trails into API endpoints later.
- Spatie Activitylog is the Laravel de facto standard (same ecosystem as Spatie Permission, already vetted).

## Architecture

### Custom Activity Model
`app/Models/Activity.php`
- Extends `Spatie\Activitylog\Models\Activity`
- Uses `BelongsToTenant` (auto-fills `tenant_id` on create, global scope on read)
- Override `$guarded` to include `tenant_id`

### Migration
`database/migrations/2026_06_17_203705_create_activity_log_table.php`
- Published from Spatie; modified to add:
  - `$table->uuid('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete()->before('log_name');`
  - RLS: ENABLE + FORCE + 4 policies (SELECT/INSERT/UPDATE/DELETE) using `public.current_tenant_id()`

### Config
`config/activitylog.php`
- `'activity_model' => App\Models\Activity::class`
- `'clean_after_days' => 365` (default, fine for now)

### Auditable Trait (Thin Wrapper)
`app/Models/Concerns/Auditable.php`
- Wraps `Spatie\Activitylog\Models\Concerns\LogsActivity`
- Overrides `getActivitylogOptions()` with sensible defaults:
  - Log all fillable attributes (except `tenant_id`, `created_at`, `updated_at`, `deleted_at`)
  - Log only dirty on update
  - Auto-description: `"{model}: {eventName}"` (e.g., `"WorkOrder: created"`)
- Models only need `use Auditable` to start logging

### Model Registration
Add `Auditable` to key models initially (configurable):
- `WorkOrder` (extends TenantModel — already has no extra logging)
- `Contact` (extends TenantModel)
- `Asset` (extends TenantModel)
- `Item` (extends TenantModel)
- `Invoice` (already exists in Facturacion)
- Add more as needed later

## Non-Goals
- Request-level logging middleware (IP, user-agent, URL for every request). Future concern.
- Audit log viewer UI in panels. Activities are queryable via `$model->activitiesAsSubject()`.
- Activity retention per-tenant (global 365 days via `activitylog:clean` is fine for MVP).

## Implementation Plan

### Files Modified/Created
| File | Change |
|------|--------|
| `app/Models/Activity.php` | Create (custom Spatie Activity model) |
| `app/Models/Concerns/Auditable.php` | Create (thin wrapper trait) |
| `config/activitylog.php` | Update `activity_model` → `App\Models\Activity::class` |
| `database/migrations/2026_06_17_203705_create_activity_log_table.php` | Add tenant_id FK + RLS |
| `app/Modules/Talleres/Models/WorkOrder.php` | Add `use Auditable` |
| `app/Models/Contact.php` | Add `use Auditable` |
| `app/Modules/Talleres/Models/Asset.php` | Add `use Auditable` |
| `app/Models/Item.php` | Add `use Auditable` |
| `tests/Feature/Security/ActivityLogRlsTest.php` | Create (RLS isolation tests) |
| `tests/Feature/ActivityLogAppScopeTest.php` | Create (app-scope: logging on CRUD) |
| `engram.json` | Update v1.10.0 |

## Schema (DDL)
```sql
ALTER TABLE activity_log ADD COLUMN tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE;

ALTER TABLE activity_log ENABLE ROW LEVEL SECURITY;
ALTER TABLE activity_log FORCE ROW LEVEL SECURITY;

CREATE POLICY tenant_isolation_select ON activity_log
    FOR SELECT USING (tenant_id = public.current_tenant_id());
CREATE POLICY tenant_isolation_insert ON activity_log
    FOR INSERT WITH CHECK (tenant_id = public.current_tenant_id());
CREATE POLICY tenant_isolation_update ON activity_log
    FOR UPDATE USING (tenant_id = public.current_tenant_id());
CREATE POLICY tenant_isolation_delete ON activity_log
    FOR DELETE USING (tenant_id = public.current_tenant_id());
```

## Tests

### `tests/Feature/ActivityLogAppScopeTest.php`
1. Creating a model logs an activity record
2. Updating a model logs attribute changes (old + new)
3. Deleting a model logs a deletion event
4. Activity log has causer = authenticated user
5. Activity log is scoped to the correct tenant

### `tests/Feature/Security/ActivityLogRlsTest.php`
1. Cannot read other tenant's activity logs via pgsql-rls
2. Cannot update other tenant's activity logs via pgsql-rls
3. Insert without tenant context fails (RLS policy)

## DoD Checklist
- [x] FEATURE_SPEC.md approved (GATE 0)
- [x] Migration modified with tenant_id + RLS → executed (nullableUuidMorphs for UUID compatibility)
- [x] Custom Activity model created + registered in config (ignoresMissingTenantContext para tests legacy)
- [x] Auditable trait created + applied to WorkOrder, Contact, Asset, Item
- [x] 5 app-scope tests + 3 RLS tests → green
- [x] Full suite: 258 tests, 258 passed, 649 assertions
- [x] Pint: 2 files fixed (import ordering, spacing)
- [x] engram.json v1.10.0
- [ ] PR → CI → merge → cleanup

### Desviaciones del plan original
- Se requirio `nullableUuidMorphs` en vez de `nullableMorphs` porque el proyecto usa UUIDs, no BIGINT auto-increment
- Se agrego `$ignoresMissingTenantContext` a BelongsToTenant trait para que Activity model no lance en tests legacy sin contexto de tenant
- 258 tests finales (no 258+): exactamente 258 porque las nuevas pruebas de ActivityLog casan exactamente con el plan
