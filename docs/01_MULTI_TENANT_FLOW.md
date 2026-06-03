01 multi tenant flow · MDCopiar01 — Flujo Multi-Tenant: RLS + TenantManager
ProyectDashboard · Jaosoft Engineering Standards v1.0

Resumen Ejecutivo
El aislamiento multi-tenant en ProyectDashboard se implementa en dos capas independientes:

PostgreSQL RLS (capa de datos) — obligatoria, no eludible
Laravel BelongsToTenant trait (capa de aplicación) — segunda línea de defensa

Si capa 1 falla (bug en política), capa 2 bloquea. Si capa 2 falla (desarrollador descuidado), capa 1 bloquea. Las dos capas deben existir siempre.

1. Arquitectura de la Variable de Sesión
¿Por qué app.current_tenant_id en PG y no en PHP?
Porque en un entorno de pool de conexiones (PgBouncer, RDS Proxy), varias requests PHP pueden reutilizar la misma conexión PostgreSQL. Si el tenant_id vive solo en PHP, un query de otro tenant puede ejecutarse en una conexión que todavía tiene el contexto anterior.
PostgreSQL garantiza que set_config('app.current_tenant_id', ...) es local a la transacción/sesión actual cuando se usa is_local = true.
La Función Central en PostgreSQL
sql-- migrations/sql/functions/current_tenant_id.sql
CREATE OR REPLACE FUNCTION public.current_tenant_id()
RETURNS UUID
LANGUAGE plpgsql
STABLE
AS $$
DECLARE
    v_tenant_id TEXT;
BEGIN
    v_tenant_id := current_setting('app.current_tenant_id', true);

    -- Validación: si es NULL o vacío, denegar acceso
    IF v_tenant_id IS NULL OR v_tenant_id = '' THEN
        RAISE EXCEPTION 'tenant_context_missing: No tenant ID in session'
            USING ERRCODE = 'P0001';
    END IF;

    -- Validación: formato UUID v4
    IF v_tenant_id !~ '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$' THEN
        RAISE EXCEPTION 'tenant_context_invalid: Malformed UUID'
            USING ERRCODE = 'P0002';
    END IF;

    RETURN v_tenant_id::UUID;
END;
$$;

-- Revocar acceso público por defecto
REVOKE EXECUTE ON FUNCTION public.current_tenant_id() FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.current_tenant_id() TO authenticated;
Regla: Esta función es el único lugar donde se valida el formato del tenant_id en PostgreSQL. No duplicar esta lógica en políticas individuales.

2. TenantManager — El Servicio Laravel
Ubicación
app/
└── Services/
    └── TenantManager.php
Implementación Completa
php<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * TenantManager
 *
 * Responsabilidad única: inyectar y limpiar el contexto
 * de tenant en la sesión de PostgreSQL.
 *
 * NO maneja autenticación. NO valida permisos de negocio.
 * Solo garantiza que app.current_tenant_id existe y es válido
 * antes de que cualquier query RLS se ejecute.
 */
final class TenantManager
{
    private ?string $currentTenantId = null;

    /**
     * Establece el contexto de tenant en la conexión PG actual.
     *
     * IMPORTANTE: is_local=true hace que la variable se resetee
     * automáticamente al final de la transacción. En conexiones
     * fuera de transacción, persiste hasta el final de la sesión.
     * El middleware debe llamar clearTenantContext() explícitamente.
     */
    public function setTenantContext(string $tenantId): void
    {
        if (!Str::isUuid($tenantId)) {
            throw new RuntimeException(
                "Invalid tenant UUID format: {$tenantId}"
            );
        }

        // Doble escape: primero validamos el formato UUID,
        // luego usamos parámetros preparados vía DB::statement
        DB::statement(
            "SELECT set_config('app.current_tenant_id', ?, false)",
            [$tenantId]
        );

        $this->currentTenantId = $tenantId;
    }

    /**
     * Limpia el contexto de tenant al finalizar la request.
     * DEBE llamarse en el middleware, no solo al hacer login.
     */
    public function clearTenantContext(): void
    {
        DB::statement(
            "SELECT set_config('app.current_tenant_id', '', false)"
        );
        $this->currentTenantId = null;
    }

    public function getCurrentTenantId(): ?string
    {
        return $this->currentTenantId;
    }

    public function hasContext(): bool
    {
        return $this->currentTenantId !== null;
    }
}
Registro en el Service Container
php// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(TenantManager::class, fn () => new TenantManager());
}

3. El Middleware: SetTenantContext
Este middleware es el punto de entrada del ciclo de tenant. Se ejecuta antes de cualquier controller.
php<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class SetTenantContext
{
    public function __construct(
        private readonly TenantManager $tenantManager
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Solo procesar si hay usuario autenticado
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $tenantId = $user->tenant_id; // UUID v4 en la tabla users

        if (empty($tenantId)) {
            abort(403, 'User has no tenant assignment.');
        }

        $this->tenantManager->setTenantContext((string) $tenantId);

        try {
            $response = $next($request);
        } finally {
            // SIEMPRE limpiar, incluso si hay excepción
            $this->tenantManager->clearTenantContext();
        }

        return $response;
    }
}
Registro del Middleware
php// bootstrap/app.php (Laravel 12)
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', [
        \App\Http\Middleware\SetTenantContext::class,
    ]);
    $middleware->appendToGroup('api', [
        \App\Http\Middleware\SetTenantContext::class,
    ]);
})

4. Políticas RLS en PostgreSQL
Plantilla Estándar para Toda Tabla con tenant_id
sql-- Aplicar a cada tabla que tenga tenant_id

-- Habilitar RLS
ALTER TABLE {tabla} ENABLE ROW LEVEL SECURITY;

-- CRÍTICO: también aplica al owner (previene bypass por migraciones)
ALTER TABLE {tabla} FORCE ROW LEVEL SECURITY;

-- Política de SELECT
CREATE POLICY "{tabla}_tenant_isolation_select"
ON {tabla}
FOR SELECT
USING (tenant_id = public.current_tenant_id());

-- Política de INSERT
CREATE POLICY "{tabla}_tenant_isolation_insert"
ON {tabla}
FOR INSERT
WITH CHECK (tenant_id = public.current_tenant_id());

-- Política de UPDATE
CREATE POLICY "{tabla}_tenant_isolation_update"
ON {tabla}
FOR UPDATE
USING (tenant_id = public.current_tenant_id())
WITH CHECK (tenant_id = public.current_tenant_id());

-- Política de DELETE
CREATE POLICY "{tabla}_tenant_isolation_delete"
ON {tabla}
FOR DELETE
USING (tenant_id = public.current_tenant_id());
¿Por qué FORCE ROW LEVEL SECURITY?
Sin FORCE, el owner de la tabla (típicamente el usuario de la app o el superuser que corre las migraciones) bypasea RLS por defecto. En desarrollo local con Docker esto puede enmascarar bugs de seguridad que solo aparecen en producción.
La Trampa de PgBouncer
⚠️  ADVERTENCIA CRÍTICA — POOLING DE CONEXIONES

Si usas PgBouncer en modo "transaction pooling":
- set_config('app.current_tenant_id', valor, false) → persiste
  solo durante la transacción, NO en toda la sesión.
- Debes usar is_local=TRUE para que el contexto se limpie
  al finalizar cada transacción.

Si usas PgBouncer en modo "session pooling" o conexión directa:
- is_local=FALSE es aceptable, pero el middleware DEBE
  limpiar el contexto explícitamente (ya implementado arriba).

Configuración Docker de ProyectDashboard → conexión directa PG.
No usar PgBouncer sin revisar este comportamiento primero.

5. Tabla tenants — Estructura Mínima
sqlCREATE TABLE tenants (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(100) UNIQUE NOT NULL,
    plan        VARCHAR(50) NOT NULL DEFAULT 'free',
    is_active   BOOLEAN NOT NULL DEFAULT true,
    settings    JSONB NOT NULL DEFAULT '{}',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- La tabla tenants NO tiene RLS sobre sí misma por tenant_id
-- (es la tabla raíz). Se protege a nivel de aplicación.
-- Solo el superadmin de Jaosoft puede listar todos los tenants.

6. Checklist de Verificación Post-Migración
Ejecutar este SQL después de cada migración que cree tablas con tenant_id:
sql-- Verificar que RLS está habilitado en todas las tablas relevantes
SELECT
    t.schemaname,
    t.tablename,
    t.rowsecurity AS rls_enabled,
    t.forceroworlepolicies AS force_rls,
    COUNT(p.policyname) AS policy_count
FROM pg_tables t
LEFT JOIN pg_policies p ON p.tablename = t.tablename
WHERE t.schemaname = 'public'
  AND t.tablename NOT IN ('tenants', 'migrations', 'failed_jobs', 'jobs')
  AND EXISTS (
      SELECT 1 FROM information_schema.columns c
      WHERE c.table_name = t.tablename
        AND c.column_name = 'tenant_id'
  )
GROUP BY t.schemaname, t.tablename, t.rowsecurity, t.forceroworlepolicies
ORDER BY t.tablename;

-- Resultado esperado: rls_enabled=true, force_rls=true, policy_count>=4
-- Si alguna fila falla → la migración está incompleta. NO deployar.

7. Diagrama de Flujo Completo
HTTP Request
    │
    ▼
[Middleware: SetTenantContext]
    │
    ├─ Auth::check() = false → skip (request pública)
    │
    └─ Auth::check() = true
           │
           ├─ Extrae user->tenant_id
           ├─ Valida UUID v4 (TenantManager)
           ├─ DB::statement("SET app.current_tenant_id = ?")
           │
           ▼
    [Controller / Action]
           │
           └─ Cualquier query Eloquent
                  │
                  ▼
           [PostgreSQL]
                  │
                  ├─ RLS evalúa: tenant_id = current_tenant_id()
                  ├─ current_tenant_id() lee app.current_tenant_id
                  ├─ Si vacío → RAISE EXCEPTION → 500
                  └─ Si match → retorna filas
                        │
                        ▼
                  Response
                        │
                        ▼
           [Middleware: finally block]
                  │
                  └─ clearTenantContext() → app.current_tenant_id = ''

Ver también: 02_ELOQUENT_AND_MIGRATIONS.md para el trait BelongsToTenant