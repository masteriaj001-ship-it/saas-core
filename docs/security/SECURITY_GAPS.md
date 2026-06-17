# SECURITY GAPS — Auditoría RLS 2026-06-17

> **Contexto:** El proyecto usa PostgreSQL RLS (Row Level Security) con la función `current_tenant_id()` que lee `app.current_tenant_id` del contexto de sesión. La conexión de desarrollo usa el usuario `sail` que tiene `BYPASSRLS = true`, lo que invalida RLS en desarrollo.

---

## Resumen

| ID | Hallazgo | Severidad | Fix requiere |
|---|---|---|---|
| GAP-001 | Usuario sail tiene BYPASSRLS = true | ✅ Fix aplicado | app_user con NOBYPASSRLS + conexión pgsql-rls |
| GAP-002 | Superadmin no establece app.current_tenant_id | ✅ Fix aplicado | SetTenantContext resuelve tenant via Filament y setea contexto para superadmin |
| GAP-003 | Jobs no establecen contexto de tenant | ✅ Fix aplicado | BelongsToTenantJob trait + SetTenantContextForJob middleware |
| GAP-004 | Tests no ejercitan RLS real | ✅ Fix aplicado | TenantManager sincroniza pgsql-rls automáticamente |
| GAP-005 | current_tenant_id() sin fallback | ✅ Fix aplicado | Migración 0000_00_00_000002: current_tenant_id_or_null() creada |

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

## GAP-002: Superadmin sin tenant context (✅ FIX APLICADO 2026-06-17)

**Hallazgo:** El middleware `SetTenantContext` omitía superadmin completamente:
```php
// Antes
if ($user->is_superadmin) {
    return $next($request);
}
```

**Fix aplicado:** Cuando el usuario es superadmin y Filament tiene un tenant resuelto (panel `/admin/{tenant:slug}`), el middleware establece `app.current_tenant_id` a ese tenant. Si no hay tenant resuelto (panel `/superadmin`, API, comandos), mantiene el comportamiento de omitir contexto — correcto para operaciones globales.

```php
// Después
if ($user->is_superadmin) {
    $tenant = $this->resolveTenant(); // Filament::getTenant()
    if ($tenant !== null) {
        $this->tenantManager->setTenantContext((string) $tenant->id);
    }
    return $next($request);
}
```

**Complemento:** Se agregó `TenantManager::withoutTenantContext(callable)` para operaciones que necesitan limpiar contexto temporalmente sin el patrón frágil manual clear/set.

**Archivos modificados:**
- `app/Http/Middleware/SetTenantContext.php` — Lógica de superadmin con tenant resoluble
- `app/Services/TenantManager.php` — Método `withoutTenantContext()`
- `app/Filament/Superadmin/Resources/TenantResource/Pages/CreateTenant.php` — `afterCreate()` limpia contexto

**Tests:** 8 tests (5 app-scope + 3 RLS) en `tests/Feature/Security/SuperadminContext*Test.php`.

---

## GAP-003: Jobs sin tenant context (✅ FIX APLICADO 2026-06-17)

**Hallazgo:** Jobs en cola no establecen `app.current_tenant_id`. El global scope de `BelongsToTenant` retorna sin filtrar si no hay contexto. Un job que procese datos de un tenant específico no tendría RLS ni scope activo.

**Fix aplicado:** Se crearon dos componentes que trabajan juntos:

1. **`BelongsToTenantJob` trait** (`app/Models/Concerns/BelongsToTenantJob.php`): cualquier job que use este trait captura automáticamente el `tenantId` del contexto actual al momento del `dispatch`. Si no hay contexto, `tenantId` queda `null`.

2. **`SetTenantContextForJob` middleware** (`app/Jobs/Middleware/SetTenantContextForJob.php`): middleware de cola que, antes del `handle()`, setea `app.current_tenant_id` si el job tiene `tenantId`. Después del `handle()`, siempre limpia el contexto para evitar contaminación entre jobs.

**Uso desde código nuevo:**
```php
use App\Models\Concerns\BelongsToTenantJob;

class ProcessSomethingJob implements ShouldQueue
{
    use BelongsToTenantJob, Dispatchable, InteractsWithQueue, SerializesModels;

    public function handle(): void
    {
        // context ya seteado automáticamente
        $this->someModel->update([...]);
    }
}
```

**Archivos creados:**
- `app/Models/Concerns/BelongsToTenantJob.php` — Trait para jobs
- `app/Jobs/Middleware/SetTenantContextForJob.php` — Middleware de cola
- `tests/Doubles/Jobs/WithTenantContextJob.php` — Test double
- `tests/Feature/Security/TenantJobContextAppScopeTest.php` — 4 tests

---

## GAP-004: Tests no ejercitan RLS real (✅ FIX APLICADO 2026-06-17)

**Hallazgo:** `TenantManager::setTenantContext()` solo seteaba `app.current_tenant_id` en la conexión `pgsql` (usuario `sail`, `BYPASSRLS=true`). La conexión `pgsql-rls` (`app_user`, `NOBYPASSRLS`) nunca recibía el contexto. Los tests RLS duplicaban la lógica con helpers privados `setRlsContext()` / `clearRlsContext()`.

**Consecuencia:** Los cientos de tests existentes que llaman `setTenantContext()` solo probaban el Eloquent scope, no PostgreSQL RLS real.

**Fix aplicado:**

1. **TenantManager sincroniza ambas conexiones**: `setTenantContext()` y `clearTenantContext()` ahora también setean/limpian `app.current_tenant_id` en la conexión `pgsql-rls` cuando está configurada. En producción no hay impacto (la conexión `pgsql-rls` solo existe en test).

2. **Helpers duplicados eliminados**: `TenantModuleRlsTest` y `SuperadminContextRlsTest` ya no tienen `setRlsContext()`/`clearRlsContext()`. Usan `TenantManager` directamente.

3. **Test de sincronización**: `test_set_context_syncs_both_connections` en `SuperadminContextRlsTest` verifica que ambas conexiones tienen el mismo contexto.

**Impacto:** Todos los tests que llaman `setTenantContext()` ahora ejercitan RLS real automáticamente. Si alguna policy RLS estuviera mal configurada, los tests existentes lo detectarían.

---

## GAP-005: Función current_tenant_id() sin fallback (✅ FIX APLICADO 2026-06-17)

**Hallazgo:**
```sql
IF v_tenant_id IS NULL OR v_tenant_id = '' THEN
    RAISE EXCEPTION 'tenant_context_missing: No tenant ID in session'
        USING ERRCODE = 'P0001';
END IF;
```
La función `current_tenant_id()` explota sin contexto. No hay modo "sin tenant" para operaciones globales.

**Consecuencia:** Migraciones, seeders, y operaciones de superadmin sin contexto fallan con excepción.

**Fix aplicado:** Migración `0000_00_00_000002_create_current_tenant_id_or_null_function` creó `current_tenant_id_or_null()` que retorna `NULL` en vez de lanzar excepción cuando no hay contexto. Usa `EXCEPTION WHEN OTHERS THEN RETURN NULL` para falla silenciosa.

⚠️ **ADVERTENCIA:** Esta función NO debe reemplazar `current_tenant_id()` en políticas RLS existentes. Es paralela, para uso exclusivo en contextos donde NULL es válido (superadmin, jobs globales, seeders). Usarla en una policy RLS de datos multi-tenant desactiva el aislamiento silenciosamente.

**Archivos:**
- `database/migrations/0000_00_00_000002_create_current_tenant_id_or_null_function.php`
- Tests en `SuperadminContextRlsTest` (3 tests: null sin contexto, UUID con contexto, current_tenant_id sigue lanzando)

---

## Referencias

- `AGENTS.md` — Sección "RLS Awareness & Security Gaps" con reglas para código nuevo
- `tests/Feature/Security/RlsCrossTenantTest.php` — 3 passed, 2 skipped (GAP-001)
- `tests/Feature/Security/SuperadminContextAppScopeTest.php` — 5 tests app-scope (GAP-002)
- `tests/Feature/Security/SuperadminContextRlsTest.php` — 3 tests RLS (GAP-005)
- `tests/Feature/Security/TenantJobContextAppScopeTest.php` — 4 tests (GAP-003)
- `tests/Doubles/Jobs/WithTenantContextJob.php` — Test double para GAP-003
- `database/migrations/0000_00_00_000001_create_current_tenant_id_function.php` — Función PostgreSQL original
- `database/migrations/0000_00_00_000002_create_current_tenant_id_or_null_function.php` — Función con fallback NULL (GAP-005)
- `app/Models/Concerns/BelongsToTenantJob.php` — Trait para jobs multi-tenant (GAP-003)
- `app/Jobs/Middleware/SetTenantContextForJob.php` — Middleware de cola (GAP-003)
- `app/Http/Middleware/SetTenantContext.php` — Middleware que establece contexto (GAP-002)
- `app/Services/TenantManager.php` — Manager de contexto + withoutTenantContext()
- `app/Models/Concerns/BelongsToTenant.php` — Trait con global scope
- `app/Filament/Superadmin/Resources/TenantResource/Pages/CreateTenant.php` — afterCreate limpia contexto
- `features/gap-003-tenant-job-context/FEATURE_SPEC.md` — Spec completo con deviations documentadas

---

## Prioridad de Fix

| Prioridad | GAP | Requisito |
|---|---|---|
| ✅ Fix aplicado | GAP-001 | app_user con NOBYPASSRLS + pgsql-rls (2026-06-17) |
| ✅ Fix aplicado | GAP-002 | SetTenantContext resuelve tenant para superadmin (2026-06-17) |
| ✅ Fix aplicado | GAP-005 | current_tenant_id_or_null creada (2026-06-17) |
| ✅ Fix aplicado | GAP-003 | BelongsToTenantJob trait + SetTenantContextForJob middleware (2026-06-17) |
| ✅ Fix aplicado | GAP-004 | TenantManager sincroniza pgsql-rls (2026-06-17) |

> **Nota:** Ningún fix se ejecutará sin autorización explícita de John ("APROBADO" literal). Fecha de próxima auditoría: 2026-09-01.

---

## USR-001: Multi-Factor Authentication para Superadmin (✅ IMPLEMENTADO 2026-06-17)

**Contexto:** Superadmins tienen acceso irrestricto a todos los tenants. Una credencial comprometida sin 2FA puede bypassear todo el trabajo de RLS.

**Implementación:** Usa el MFA incorporado de Filament v5 (TOTP via Google Authenticator + código por email).

| Componente | Detalle |
|---|---|
| Interfaz `HasAppAuthentication` | `getAppAuthenticationSecret()`, `saveAppAuthenticationSecret()`, `getAppAuthenticationHolderName()` |
| Interfaz `HasAppAuthenticationRecovery` | `getAppAuthenticationRecoveryCodes()`, `saveAppAuthenticationRecoveryCodes()` |
| Interfaz `HasEmailAuthentication` | `hasEmailAuthentication()`, `toggleEmailAuthentication()` |
| Almacenamiento | `two_factor_secret` encryptado via `Crypt`, `two_factor_recovery_codes` en JSON |
| Superadmin panel | `multiFactorAuthentication(isRequired: true)` — forzoso |
| Admin panel | `multiFactorAuthentication(isRequired: false)` — opcional |

**Próximos pasos:** Configurar recovery email y políticas de reset de MFA para superadmin.
