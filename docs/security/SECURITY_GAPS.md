# SECURITY GAPS — Auditoría RLS 2026-06-17

> **Contexto:** El proyecto usa PostgreSQL RLS (Row Level Security) con la función `current_tenant_id()` que lee `app.current_tenant_id` del contexto de sesión. La conexión de desarrollo usa el usuario `sail` que tiene `BYPASSRLS = true`, lo que invalida RLS en desarrollo.

---

## Resumen

| ID | Hallazgo | Severidad | Fix requiere |
|---|---|---|---|
| GAP-001 | Usuario sail tiene BYPASSRLS = true | ✅ Fix aplicado | app_user con NOBYPASSRLS + conexión pgsql-rls |
| GAP-002 | Superadmin no establece app.current_tenant_id | 🟡 Medio | Arquitectura de conexión dual o contexto especial |
| GAP-003 | Jobs no establecen contexto de tenant | 🟡 Medio | Refactor de job dispatch |
| GAP-004 | Tests no ejercitan RLS real | 🟡 Medio | Agregar setTenantContext en setUp() |
| GAP-005 | current_tenant_id() sin fallback | 🟡 Medio | Crear current_tenant_id_or_null() para operaciones globales |

---

## GAP-001: BYPASSRLS en usuario de desarrollo

**Hallazgo:**
```sql
SELECT rolname, rolbypassrls FROM pg_roles WHERE rolname = 'sail';
-- sail | t
```
El usuario `sail` (desarrollo) tiene `BYPASSRLS = true`. RLS está habilitado en tablas pero ignorado completamente por la conexión actual.

**Consecuencia:** Tests que verifican "tenant A no ve datos de B" solo prueban el global scope de Eloquent (`BelongsToTenant`), no la capa RLS de PostgreSQL. Los tests `RlsCrossTenantTest` tests 2 y 3 estaban SKIPPED.

**Fix aplicado (2026-06-17):** Migración `2026_06_17_151704_create_app_user_role` creó usuario `app_user` con `NOBYPASSRLS`. Conexión `pgsql-rls` en `config/database.php`. Tests 2 y 3 de `RlsCrossTenantTest` ahora ejercitan RLS real. Tests 1, 4, 5 existentes usan usuario `sail` (BYPASSRLS) para probar Eloquent scope.

---

## GAP-002: Superadmin sin tenant context

**Hallazgo:** El middleware `SetTenantContext` omite superadmin:
```php
if ($user->is_superadmin) {
    return $next($request);
}
```
Superadmin nunca establece `app.current_tenant_id`.

**Consecuencia:** Superadmin que use query directa (`DB::select`, `DB::table`) sin Eloquent scope → RLS lanza excepción `tenant_context_missing`.

**Fix:** Opción A: establecer `app.current_tenant_id` para superadmin con valor especial. Opción B: conexión separada sin RLS para superadmin.

---

## GAP-003: Jobs sin tenant context

**Hallazgo:** Jobs en cola no establecen `app.current_tenant_id`. El global scope de `BelongsToTenant` retorna sin filtrar si no hay contexto.

**Consecuencia:** Job con query directa sin `setTenantContext()` → RLS explota. Job con `withoutGlobalScope('tenant')` → expone datos de todos los tenants (si RLS está bypassed).

**Fix:** Establecer `TenantManager::setTenantContext()` al inicio de cada job, o usar conexión sin RLS para jobs multi-tenant.

---

## GAP-004: Tests no ejercitan RLS real

**Hallazgo:** Los tests de tenant isolation (`TallerTenantIsolationTest`, `WorkOrderTenantIsolationTest`) nunca llaman `TenantManager::setTenantContext()`. Solo verifican el Eloquent scope.

**Consecuencia:** Si se migra a usuario con `NOBYPASSRLS`, tests pueden fallar masivamente.

**Fix:** Agregar `TenantManager::setTenantContext($tenant->id)` en `setUp()` de tests de tenant isolation. Los tests `RlsCrossTenantTest` tests 1, 4, 5 ya usan este patrón como referencia.

---

## GAP-005: Función current_tenant_id() sin fallback

**Hallazgo:**
```sql
IF v_tenant_id IS NULL OR v_tenant_id = '' THEN
    RAISE EXCEPTION 'tenant_context_missing: No tenant ID in session'
        USING ERRCODE = 'P0001';
END IF;
```
La función `current_tenant_id()` explota sin contexto. No hay modo "sin tenant" para operaciones globales.

**Consecuencia:** Migraciones, seeders, y operaciones de superadmin sin contexto fallan con excepción.

**Fix:** Crear función `current_tenant_id_or_null()` para operaciones que permiten NULL, y modificar policies para manejar NULL explícitamente donde tenga sentido.

---

## Referencias

- `AGENTS.md` — Sección "RLS Awareness & Security Gaps" con reglas para código nuevo
- `tests/Feature/Security/RlsCrossTenantTest.php` — 3 passed, 2 skipped (GAP-001)
- `database/migrations/0000_00_00_000001_create_current_tenant_id_function.php` — Función PostgreSQL
- `app/Http/Middleware/SetTenantContext.php` — Middleware que establece contexto
- `app/Services/TenantManager.php` — Manager de contexto
- `app/Models/Concerns/BelongsToTenant.php` — Trait con global scope

---

## Prioridad de Fix

| Prioridad | GAP | Requisito |
|---|---|---|
| ✅ Fix aplicado | GAP-001 | app_user con NOBYPASSRLS + pgsql-rls (2026-06-17) |
| 🟡 Antes de jobs multi-tenant | GAP-003 | Refactor job dispatch |
| 🟡 Antes de superadmin con queries directas | GAP-002 | Arquitectura de conexión dual |
| 🟡 Antes de migrar a RLS real | GAP-004 | Actualizar tests existentes |
| 🟡 Mejora continua | GAP-005 | Función con fallback |

> **Nota:** Ningún fix se ejecutará sin autorización explícita de John ("APROBADO" literal). Fecha de próxima auditoría: 2026-09-01.
