# ADR 001: Arquitectura Multi-tenant

**Estado:** Aceptado | **Fecha:** 2026-06-17 | **Autor:** John + Agentes IA

---

## Contexto

Se evaluaron dos enfoques de arquitectura multi-tenant tras revisar propuestas de Kimi y Claude:

- **Enfoque A (Kimi):** Usuario global sin `tenant_id`, pivot `tenant_user`, Inertia + Vue, Octane, `app('current.tenant')` helper.
- **Enfoque B (Actual):** `User.tenant_id` directo, `TenantManager` service con contexto explícito, Filament 5 (Livewire), FPM.

## Decisión

Se mantiene el **Enfoque B (actual)** con las siguientes adiciones:

1. `tenant_modules` — activación condicional de módulos por tenant
2. Middleware `module:{key}` — protección de rutas por módulo activo

## Opciones Consideradas

| Opción | Pros | Contras |
|---|---|---|
| User global + pivot (Kimi) | Un usuario en múltiples tenants | Migración masiva de código existente. Sin cliente real que lo exija. |
| User.tenant_id actual | Simple, 212 tests pasando, implementado | Un usuario = un tenant. Escenario grupo empresarial requiere refactor futuro. |
| Octane (Kimi) | Conexiones persistentes, alta concurrencia | Complejidad de deployment, memory leaks, sesiones. Sin necesidad actual. |
| Inertia + Vue (Kimi) | SPA feel, API lista | Reescribir Filament 5 → 3 meses. Filament es más rápido para ERP. |

## Consecuencias

- **Positivas:** Código actual continúa sin refactor. `tenant_modules` agrega valor inmediato sin romper nada.
- **Negativas:** Escenario "mismo usuario en dos talleres" requerirá migración a pivot `tenant_user` cuando un cliente real lo pida.
- **Neutras:** La migración a pivot está documentada pero no implementada. Cuando llegue, se agrega tabla `tenant_user` sin eliminar `User.tenant_id`.

## Reglas Derivadas

1. `User.tenant_id` se mantiene como FK directa. No se migra a pivot hasta que un cliente real lo exija.
2. `TenantManager` service se mantiene como fuente de verdad para contexto de tenant.
3. Los módulos verticales se registran en `tenant_modules`. Ruta sin módulo activo → 403.
4. JSONB se usa solo para `settings` y `metadata`. Datos estructurados van en columnas o tablas de extensión.

## Dual-Connection Testing Strategy

El proyecto usa dos conexiones de base de datos para testing con roles PostgreSQL distintos:

| Conexión | Usuario | RLS | Propósito |
|---|---|---|---|
| `pgsql` (default) | `sail` | `BYPASSRLS=true` | Tests de lógica de aplicación, migraciones, seeders |
| `pgsql-rls` | `app_user` | `NOBYPASSRLS` | Tests de seguridad RLS real |

### ¿Por qué no usar un solo usuario?

`sail` necesita `BYPASSRLS` para migraciones, seeders y limpieza de tests. Si `app_user` fuera default, los Feature tests normales fallarían sin contexto RLS configurado.

### Patrón de test RLS

```php
// 1. Seed data via default connection (sail bypasses RLS)
DB::table('tenant_modules')->insert([...]);

// 2. Set tenant context on the RLS connection
DB::connection('pgsql-rls')->statement(
    "SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]
);

// 3. Query as the target tenant via pgsql-rls
$rows = DB::connection('pgsql-rls')
    ->table('tenant_modules')
    ->where('module_slug', 'taller')
    ->get();

// 4. Assert isolation
$this->assertCount(0, $rows);
```

### Anti-patrón: assertDatabaseMissing no prueba RLS

```php
// MAL — usa conexión default (sail/BYPASSRLS), pasa aunque RLS esté roto
$this->assertDatabaseMissing('tenant_modules', [...]);

// BIEN — usa app_user/NOBYPASSRLS, prueba RLS real
$rows = DB::connection('pgsql-rls')->select(...);
$this->assertCount(0, $rows);
```

### Convención de nombrado

| Sufijo | Contenido | Conexión |
|---|---|---|
| `*AppScopeTest.php` | Lógica de aplicación (global scope Eloquent) | `pgsql` |
| `*RlsTest.php` | Seguridad RLS real | `pgsql-rls` |

### CI enforcement

Los tests `*RlsTest.php` y `RlsCrossTenantTest` no son opcionales. Deben correr en el mismo pipeline que los tests de aplicación. Excluirlos para "acelerar CI" elimina la validación de seguridad silenciosamente.

## Referencias

- `AGENTS.md` — Reglas Mandatorias (Tenant Isolation, Naming Zero Redundancy)
- `app/Models/Concerns/BelongsToTenant.php` — Trait actual con fallback a Auth
- `app/Services/TenantManager.php` — Contexto explícito de tenant
- `docs/security/SECURITY_GAPS.md` — Auditoría RLS (GAP-001 fix aplicado)
- `tests/Feature/TenantModuleAppScopeTest.php` — Tests de aplicación para módulos
- `tests/Feature/Security/TenantModuleRlsTest.php` — Tests RLS real para módulos
- `tests/Feature/Security/RlsCrossTenantTest.php` — Tests RLS real cross-tenant
