# ProyectDashboard — Guía de Aprendizaje

> Stack: Laravel ^13.8 · PHP ^8.3 · PostgreSQL 16.14 · Filament ^5.6 · RLS Nativo
> Jaosoft Engineering Standards v1.0

---

## Índice

1. [Conceptos Fundamentales](#1-conceptos-fundamentales)
2. [Arquitectura del Proyecto](#2-arquitectura-del-proyecto)
3. [Las Migraciones Explicadas](#3-las-migraciones-explicadas)
4. [Los Modelos Paso a Paso](#4-los-modelos-paso-a-paso)
5. [Cómo Trabajar con Agentes IA](#5-cómo-trabajar-con-agentes-ia)
6. [Siguientes Pasos](#6-siguientes-pasos)
7. [Apéndices](#7-apéndices)

---

## 1. Conceptos Fundamentales

### 1.1 ¿Qué es multi-tenancy?

Multi-tenancy significa que **una sola aplicación sirve a múltiples clientes (tenants)**, y cada cliente solo ve sus propios datos.

**Ejemplo del mundo real:**
- Tenant A = "Taller Mecánico Pérez" → ve sus 5 vehículos, sus 20 repuestos
- Tenant B = "Constructora López" → ve sus 30 máquinas, sus 200 repuestos
- **Nunca** se cruzan los datos entre ellos

### 1.2 El doble cerrojo (defensa en profundidad)

Este proyecto usa **dos capas independientes** de seguridad multi-tenant:

```
CAPA 1 — PostgreSQL RLS (base de datos)
  ├── Es el guardián principal
  ├── Corre en el motor de BD, nadie lo bypasea
  └── FORCE ROW LEVEL SECURITY asegura que ni el owner de la tabla lo evade

CAPA 2 — Laravel Global Scope (aplicación)
  ├── Es el respaldo (segunda línea de defensa)
  ├── Si RLS fallara por un bug, el scope filtra igual
  └── Se activa automáticamente al poner el trait BelongsToTenant

Si capa 1 falla → capa 2 bloquea
Si capa 2 falla (dev se olvida del scope) → capa 1 bloquea
```

### 1.3 Zero Redundancy (nombres agnósticos)

En lugar de crear tablas con nombres específicos de industria, usamos nombres **genéricos** que funcionan para cualquier vertical:

| Nombre correcto | Qué almacena | Nombres prohibidos |
|---|---|---|
| `Asset` | Vehículos, maquinaria, equipos, propiedades | Vehicle, Machine, Equipment |
| `Item` | Repuestos, productos, servicios, materia prima | Product, Spare, RawMaterial |
| `Contact` | Clientes, proveedores, empleados | Client, Supplier, Customer |
| `Space` | Sucursales, talleres, bodegas | Branch, Location, Store |
| `Transaction` | Ventas, compras, facturas | Sale, Purchase, Invoice |

**¿Por qué?** Porque un taller automotriz y una clínica dental usan el mismo sistema. Si creas `Vehicle` para el primer cliente, el segundo cliente necesita `Equipment`. Con `Asset` + un campo `asset_type` resuelves ambos.

### 1.4 UUID vs BIGINT (PKs)

```sql
-- ❌ BIGSERIAL / AUTO_INCREMENT (prohibido)
id BIGSERIAL PRIMARY KEY  -- secuencial, expone cuántos registros tienes

-- ✅ UUID v4 (obligatorio)
id uuid PRIMARY KEY DEFAULT gen_random_uuid()  -- único global, no secuencial
```

**¿Por qué UUID?**
- No secuencial → no expones cantidad de registros
- Único globalmente → facilita migraciones y合并 de BD
- `gen_random_uuid()` de PostgreSQL es más rápido que generarlo en PHP
- Funciona con RLS sin fricción

---

## 2. Arquitectura del Proyecto

### 2.1 Árbol de directorios anotado

```
proyect-dashboard/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── CreateTenantCommand.php              ← Crea tenant + admin + roles
│   ├── Http/
│   │   ├── Middleware/
│   │   │   └── SetTenantContext.php                  ← Inyecta tenant_id en PG (web + Filament)
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── RegisterController.php            ← POST /register con industria
│   │   │   │   ├── ForgotPasswordController.php      ← GET/POST /forgot-password
│   │   │   │   ├── ResetPasswordController.php       ← GET/POST /reset-password/{token}
│   │   │   │   └── ApiTokenController.php            ← CRUD /api/sanctum/token
│   │   │   └── Controller.php                        ← Base
│   │   └── Requests/
│   │       └── WorkOrders/
│   │           ├── CreateWorkOrderRequest.php
│   │           └── UpdateWorkOrderRequest.php
│   ├── Models/
│   │   ├── TenantModel.php                           ← Base abstracta (todos extienden)
│   │   ├── Tenant.php                                ← Tabla raíz (sin RLS)
│   │   ├── User.php                                  ← Autenticación + HasRoles + HasApiTokens
│   │   ├── Role.php                                  ← Spatie custom + BelongsToTenant
│   │   ├── Permission.php                            ← Spatie custom + BelongsToTenant
│   │   ├── Asset.php                                 ← Vehículos, maquinaria, etc.
│   │   ├── Item.php                                  ← Repuestos, productos
│   │   ├── Contact.php                               ← Clientes, proveedores
│   │   ├── WorkOrder.php                             ← Órdenes de trabajo
│   │   ├── WorkOrderItem.php                         ← Items consumidos en WO
│   │   ├── Transaction.php                           ← Facturas (venta/compra)
│   │   ├── TransactionItem.php                       ← Líneas de factura
│   │   ├── ModuleCatalog.php                         ← Catálogo global de módulos (sin RLS)
│   │   ├── Category.php                              ← Categorías de items por tenant
│   │   ├── Location.php                              ← Ubicaciones por tenant (is_main)
│   │   ├── TenantModule.php                          ← Módulos activados por tenant
│   │   └── Concerns/
│   │       └── BelongsToTenant.php                   ← Trait con global scope
│   ├── Services/
│   │   ├── TenantManager.php                         ← Puente PHP → PostgreSQL
│   │   ├── Auth/
│   │   │   └── RegisterService.php                   ← Crea tenant + usuario + defaults por industria
│   │   ├── Transactions/
│   │   │   └── TransactionService.php                ← Contador facturas atómico + IVA + emitir/anular
│   │   └── WorkOrders/
│   │       └── WorkOrderService.php                  ← CRUD + generación códigos WO-XXXX
│   ├── Policies/
│   │   ├── WorkOrderPolicy.php                       ← Autorización por $user->can()
│   │   └── TransactionPolicy.php                     ← 4 métodos (viewAny/create/view/update)
│   ├── Notifications/
│   │   └── ResetPasswordNotification.php             ← Custom notification (Blade template)
│   ├── Filament/
│   │   ├── Resources/
│   │   │   ├── AssetResource.php                     ← CRUD Assets (Filament 5 Schema)
│   │   │   ├── ContactResource.php                   ← CRUD Contacts
│   │   │   ├── ItemResource.php                      ← CRUD Items
│   │   │   ├── LocationResource.php                  ← CRUD Locations (badge "Principal")
│   │   │   ├── TransactionResource.php               ← CRUD Transactions (tabs Venta/Compra)
│   │   │   └── WorkOrderResource.php                 ← CRUD WorkOrders
│   │   └── RelationManagers/
│   │       ├── ItemsRelationManager.php              ← Items dentro de WO
│   │       └── TransactionItemsRelationManager.php   ← Ítems dentro de factura
│   └── Providers/
│       ├── AppServiceProvider.php                    ← Singleton TenantManager + RateLimiter
│       └── Filament/
│           └── AdminPanelProvider.php                ← Panel admin (middleware SetTenantContext)
├── bootstrap/
│   └── app.php                                       ← Middleware registrado en grupo web
├── config/
│   ├── permission.php                                ← Spatie config (teams=false)
│   └── industry-defaults.php                         ← Defaults por industria (5 verticales)
├── database/
│   ├── factories/                                    ← 7 factories
│   ├── migrations/                                   ← 16 migraciones
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── RolePermissionSeeder.php                  ← 4 roles + 20 permisos
│       └── ModulesCatalogSeeder.php                  ← 5 módulos globales
├── docs/
│   ├── PROJECT_STATE.md                              ← Estado actual del proyecto
│   ├── LEARNING_GUIDE.md                             ← Este archivo
│   ├── WORKFLOW.md                                   ← Cómo trabajar con agentes
│   └── features/
│       ├── FEATURE_SPEC_TEMPLATE.md
│       ├── spatie-permission.md
│       ├── work_orders/FEATURE_SPEC.md
│       └── transactions/FEATURE_SPEC.md
├── resources/
│   └── views/
│       ├── auth/
│       │   ├── register.blade.php                    ← Formulario registro con industria
│       │   ├── forgot-password.blade.php
│       │   └── reset-password.blade.php
│       └── emails/
│           └── password-reset.blade.php
├── routes/
│   ├── web.php                                       ← Auth routes + named route login
│   ├── api.php                                       ← Sanctum token routes
│   └── console.php
└── tests/
    ├── Feature/
    │   ├── Security/
    │   │   ├── SpatieTenantIsolationTest.php         ← 4 tests
    │   │   ├── SpatiePermissionBypassTest.php        ← 5 tests
    │   │   ├── SpatieCacheIsolationTest.php          ← 3 tests
    │   │   └── WorkOrderTenantIsolationTest.php      ← 1 test
    │   ├── Auth/
    │   │   ├── RegistrationTest.php                  ← 12 tests
    │   │   ├── PasswordResetTest.php                 ← 7 tests
    │   │   └── ApiTokenTest.php                      ← 9 tests
    │   ├── Onboarding/
    │   │   └── RegistrationWithDefaultsTest.php      ← 6 tests
    │   ├── Transactions/
    │   │   └── TransactionTest.php                   ← 9 tests
    │   └── WorkOrders/
    │       └── WorkOrderTest.php                     ← 4 tests
    └── Unit/
        └── ExampleTest.php
```

### 2.2 Flujo completo de una request

```
USUARIO (navegador)
    │
    ▼
HTTP Request → GET /assets
    │
    ▼
SetTenantContext Middleware
    │
    ├─ Auth::check() = false → pasa sin tenant context (login público)
    │
    └─ Auth::check() = true
           │
           ├─ 1. Toma $user->tenant_id (UUID v4)
           ├─ 2. Valida formato UUID
           ├─ 3. DB::set_config('app.current_tenant_id', $tenantId)
           │
           ▼
    Controller → Asset::paginate()
           │
           ▼
    Eloquent (BelongsToTenant global scope)
           │  Agrega automáticamente: WHERE assets.tenant_id = ?
           │
           ▼
    PostgreSQL
           │
           ├─ 1. RLS evalúa: tenant_id = current_tenant_id()
           │    └─ current_tenant_id() lee app.current_tenant_id
           │    └─ Si está vacío → RAISE EXCEPTION → HTTP 500
           │    └─ Si no coincide → 0 filas
           │
           ├─ 2. El WHERE del global scope se suma al RLS
           │    (doble filtro, ambos deben pasar)
           │
           ▼
    Response JSON/HTML (solo datos de su tenant)
           │
           ▼
    SetTenantContext (finally block)
           │
           └─ clearTenantContext() → app.current_tenant_id = ''
```

### 2.3 Quién es quién

| Componente | Ubicación | Responsabilidad |
|---|---|---|
| `current_tenant_id()` | PostgreSQL (migración) | Función que retorna el tenant activo. Si no hay, lanza error. |
| `TenantManager` | `app/Services/` | Puente PHP → PG. Escribe y limpia `app.current_tenant_id`. |
| `SetTenantContext` | `app/Http/Middleware/` | Middleware que llama a TenantManager en cada request. |
| `BelongsToTenant` | `app/Models/Concerns/` | Trait que agrega global scope y auto-asigna tenant_id al crear. |
| `TenantModel` | `app/Models/` | Clase abstracta base que unifica el trait + UUID + SoftDeletes. |

---

## 2.2 MVC + Service Layer (por qué no es solo MVC)

Laravel es MVC. Pero este proyecto agrega una capa de Service:

```
MVC puro:
Request → Controller → Model → View

Este proyecto:
Request → Controller → Service → Model → View
                       ↑
              aquí vive la lógica de negocio
```

**Responsabilidad de cada capa:**

| Capa | Responsabilidad | Ejemplo |
|---|---|---|
| Controller | Recibe request, llama service, retorna response | `WorkOrderController::store()` |
| Service | Lógica de negocio, orquesta modelos | `WorkOrderService::create()` |
| Model | Datos, relaciones, scopes, casts | `WorkOrder::belongsTo(Asset::class)` |
| Policy | Autorización por rol | `WorkOrderPolicy::create()` |
| FormRequest | Validación de input | `CreateWorkOrderRequest::rules()` |

**Regla práctica:** Si un Controller tiene más de 20 líneas de lógica, esa lógica debería estar en un Service.

**¿Por qué en este proyecto es obligatorio?**
- Multi-tenancy con RLS requiere orquestación limpia
- TransactionService maneja IVA, contadores atómicos, emitir/anular
- WorkOrderService genera códigos WO-XXXX y valida estados
- RegisterService crea tenant + defaults por industria en una transacción
- Filament Resources y API comparten el mismo Service sin duplicar código

## 3. Las Migraciones Explicadas

### 3.1 Orden de ejecución (por qué importa)

Las migraciones se ejecutan en **orden alfabético por nombre de archivo**. Por eso los prefijos de fecha importan:

```
Orden   Archivo                                          Propósito
──────────────────────────────────────────────────────────────────
1      0000_00_00_000001_create_current_tenant_id_...    Función PG (debe existir antes que RLS)
2      0000_00_00_000002_create_tenants_table            Tabla raíz (debe existir antes que FK)
3      0001_01_01_000000_create_users_table              Users con FK → tenants
4      0001_01_01_000001_create_cache_table              Laravel core (intacta)
5      0001_01_01_000002_create_jobs_table               Laravel core (intacta)
6      2026_05_25_031519_create_assets_table             FK → tenants + RLS
7      2026_05_25_031520_create_items_table              FK → tenants + RLS
8      2026_05_25_031521_create_contacts_table           FK → tenants + RLS
9      2026_05_25_192256_create_work_orders_table        FK → tenants + RLS (work_orders + work_order_items)
10     2026_05_26_160000_create_permission_tables        FK → tenants + RLS (5 tablas Spatie)
11     2026_05_27_000001_create_transactions_tables      FK → tenants + RLS (transactions + transaction_items)
12     2026_05_27_000002_add_sanctum_uuid                Altera tokenable_id a UUID (FK users.id)
13     2026_05_27_000003_add_user_fields                 Agrega phone, profile_photo a users
14     2026_05_28_000001_create_modules_catalog_table    Tabla global de módulos (SIN RLS)
15     2026_05_28_000002_create_categories_table         FK → tenants + RLS
16     2026_05_28_000003_create_locations_table          FK → tenants + RLS
17     2026_05_28_000004_create_tenant_modules_table     FK → tenants + RLS
```

**Regla de oro:** La función `current_tenant_id()` y la tabla `tenants` deben existir **antes** que cualquier tabla que las referencie.

### 3.2 La función current_tenant_id() — el firewall

```sql
CREATE OR REPLACE FUNCTION public.current_tenant_id()
RETURNS UUID
LANGUAGE plpgsql
STABLE
AS $$
DECLARE
    v_tenant_id TEXT;
BEGIN
    -- Lee la variable de sesión (true = no lanza error si no existe)
    v_tenant_id := current_setting('app.current_tenant_id', true);

    -- Si está vacía → BLOQUEA todo acceso
    IF v_tenant_id IS NULL OR v_tenant_id = '' THEN
        RAISE EXCEPTION 'tenant_context_missing: No tenant ID in session';
    END IF;

    -- Valida que sea UUID v4 o v7 (formato xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
    IF v_tenant_id !~ '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' THEN
        RAISE EXCEPTION 'tenant_context_invalid: Malformed UUID';
    END IF;

    RETURN v_tenant_id::UUID;
END;
$$;
```

**¿Qué hace?** Actúa como guardia de seguridad: si no hay tenant context activo en la sesión de BD, cualquier query que pase por RLS lanza un error en lugar de devolver datos incorrectos (o vacíos).

### 3.3 La tabla tenants (nodo raíz)

```sql
CREATE TABLE tenants (
    id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name       varchar(255) NOT NULL,
    slug       varchar(100) UNIQUE NOT NULL,
    plan       varchar(50) NOT NULL DEFAULT 'free',
    is_active  boolean NOT NULL DEFAULT true,
    settings   jsonb NOT NULL DEFAULT '{}',
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
```

**Nota importante:** `tenants` NO tiene RLS ni `tenant_id`. Es la tabla raíz del sistema. Se protege a nivel de aplicación (solo superadmin puede listar todos).

### 3.4 Cada política RLS explicada

Para cada tabla de negocio (`assets`, `items`, `contacts`, `users`) creamos **4 políticas**:

```sql
-- 1. SELECT: solo ve filas de su tenant
CREATE POLICY "assets_tenant_isolation_select"
    ON assets FOR SELECT
    USING (tenant_id = public.current_tenant_id());

-- 2. INSERT: solo puede crear filas con su tenant_id
CREATE POLICY "assets_tenant_isolation_insert"
    ON assets FOR INSERT
    WITH CHECK (tenant_id = public.current_tenant_id());

-- 3. UPDATE: solo modifica filas de su tenant
CREATE POLICY "assets_tenant_isolation_update"
    ON assets FOR UPDATE
    USING (tenant_id = public.current_tenant_id())
    WITH CHECK (tenant_id = public.current_tenant_id());

-- 4. DELETE: solo elimina filas de su tenant
CREATE POLICY "assets_tenant_isolation_delete"
    ON assets FOR DELETE
    USING (tenant_id = public.current_tenant_id());
```

**¿Por qué 4 y no 1 sola?** PostgreSQL permite combinar políticas con OR. Si hicieramos una sola política `FOR ALL`, cualquier excepción en una operación afectaría a las demás. Separadas es más mantenible.

### 3.5 FORCE ROW LEVEL SECURITY

```sql
ALTER TABLE assets FORCE ROW LEVEL SECURITY;
```

Sin esta línea, el **owner de la tabla** (el usuario de BD que hizo `CREATE TABLE`) **bypasea RLS**. En producción el owner suele ser el usuario de la aplicación, y si alguien obtiene sus credenciales, tendría acceso a todos los tenants. `FORCE` elimina ese riesgo.

### 3.6 El patrón de migración estándar

Cada migración sigue esta plantilla (ejemplo con `assets`):

```php
public function up(): void
{
    // 1. CREATE TABLE con Schema Builder
    Schema::create('assets', function (Blueprint $table) {
        $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
        $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
        // ... campos de negocio ...
        $table->softDeletes();
        $table->timestamps();

        $table->index('tenant_id');                                    // RLS performance
        $table->index(['tenant_id', 'status']);                        // Queries frecuentes
    });

    // 2. RLS en la misma migración
    DB::unprepared('
        ALTER TABLE assets ENABLE ROW LEVEL SECURITY;
        ALTER TABLE assets FORCE ROW LEVEL SECURITY;
        CREATE POLICY "assets_tenant_isolation_select" ON assets ...
        CREATE POLICY "assets_tenant_isolation_insert" ON assets ...
        CREATE POLICY "assets_tenant_isolation_update" ON assets ...
        CREATE POLICY "assets_tenant_isolation_delete" ON assets ...
    ');
}

public function down(): void
{
    // 3. Reverse: limpiar políticas ANTES de dropear la tabla
    DB::unprepared('
        DROP POLICY IF EXISTS "assets_tenant_isolation_delete" ON assets;
        DROP POLICY IF EXISTS "assets_tenant_isolation_update" ON assets;
        DROP POLICY IF EXISTS "assets_tenant_isolation_insert" ON assets;
        DROP POLICY IF EXISTS "assets_tenant_isolation_select" ON assets;
        ALTER TABLE assets FORCE ROW LEVEL SECURITY;
        ALTER TABLE assets DISABLE ROW LEVEL SECURITY;
    ');
    Schema::dropIfExists('assets');
}
```

---

## 4. Los Modelos Paso a Paso

### 4.1 TenantModel (clase base abstracta)

```php
abstract class TenantModel extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $guarded = [
        'id',          // Nunca se asigna masivamente (lo genera la BD)
        'tenant_id',   // Nunca se asigna masivamente (lo inyecta el trait)
        'created_at', 'updated_at', 'deleted_at',  // Timestamps automáticos
    ];

    protected function casts(): array
    {
        return [
            'id'         => 'string',  // UUID → string, no integer
            'tenant_id'  => 'string',  // UUID → string
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
```

**Regla:** Todo modelo de negocio **debe** extender `TenantModel`. La única excepción es `User` (extiende `Authenticatable` de Laravel, por lo que usa el trait directamente).

### 4.2 BelongsToTenant (el trait)

```php
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Global scope: se aplica a TODAS las consultas del modelo
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tm = app(TenantManager::class);

            // En consola (artisan, tests) sin tenant context, no filtramos
            if (!$tm->hasContext()) {
                if (app()->runningInConsole()) return;
                throw new RuntimeException('No tenant context');
            }

            // Agrega: WHERE tabla.tenant_id = 'uuid-activo'
            $builder->where(
                $builder->getModel()->getTable() . '.tenant_id',
                $tm->getCurrentTenantId()
            );
        });

        // Auto-asignar tenant_id al crear nuevos registros
        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $tm = app(TenantManager::class);
                if (!$tm->hasContext()) {
                    throw new RuntimeException('Cannot create without tenant context');
                }
                $model->tenant_id = $tm->getCurrentTenantId();
            }
        });
    }

    // Relación directa al Tenant
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scope para saltar el filtro de tenant (solo superadmin)
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
```

### 4.3 TenantManager (el puente)

```php
final class TenantManager
{
    private ?string $currentTenantId = null;

    public function setTenantContext(string $tenantId): void
    {
        // 1. Validar UUID
        if (!Str::isUuid($tenantId)) {
            throw new RuntimeException("Invalid UUID: {$tenantId}");
        }

        // 2. Inyectar en PostgreSQL (is_local = false = persiste en la sesión)
        DB::statement(
            "SELECT set_config('app.current_tenant_id', ?, false)",
            [$tenantId]
        );

        $this->currentTenantId = $tenantId;
    }

    // Limpiar al final de la request (se llama en el finally del middleware)
    public function clearTenantContext(): void
    {
        DB::statement("SELECT set_config('app.current_tenant_id', '', false)");
        $this->currentTenantId = null;
    }

    public function getCurrentTenantId(): ?string { return $this->currentTenantId; }
    public function hasContext(): bool           { return $this->currentTenantId !== null; }
}
```

Registrado como **singleton** en `AppServiceProvider`:
```php
$this->app->singleton(TenantManager::class, fn () => new TenantManager());
```

### 4.4 SetTenantContext (middleware)

```php
final class SetTenantContext
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Solo para usuarios autenticados
        if (!Auth::check()) return $next($request);

        $tenantId = Auth::user()->tenant_id;
        if (empty($tenantId)) abort(403, 'No tenant assignment.');

        $this->tenantManager->setTenantContext((string) $tenantId);

        try {
            $response = $next($request);
        } finally {
            // SIEMPRE limpia, incluso si hay excepción
            $this->tenantManager->clearTenantContext();
        }

        return $response;
    }
}
```

**El `finally` es crítico:** si un controller lanza una excepción, el método `finally` se ejecuta igual. Sin él, la conexión PostgreSQL quedaría con `app.current_tenant_id` del request anterior, causando filtrado incorrecto en el siguiente request.

### 4.5 Los modelos concretos

```php
// Asset.php — Vehículos, maquinaria, equipos
class Asset extends TenantModel
{
    protected $fillable = ['name', 'code', 'asset_type', 'status', 'metadata', 'acquired_at'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata'    => 'array',
            'acquired_at' => 'date',
            'disposed_at' => 'date',
        ]);
    }
}

// Item.php — Repuestos, productos, servicios
class Item extends TenantModel
{
    protected $fillable = ['sku', 'name', 'description', 'item_type', 'unit', 'price', 'cost', 'stock', 'min_stock', 'metadata'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'price' => 'decimal:2', 'cost' => 'decimal:2',
            'stock' => 'integer', 'min_stock' => 'integer',
            'metadata' => 'array',
        ]);
    }
}

// Contact.php — Clientes, proveedores, empleados
class Contact extends TenantModel
{
    protected $fillable = ['contact_type', 'name', 'tax_id', 'email', 'phone', 'address', 'metadata'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['metadata' => 'array']);
    }
}

// User.php — Autenticación (excepción: extiende Authenticatable, no TenantModel)
class User extends Authenticatable
{
    use BelongsToTenant, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'password'];

    protected $casts = [
        'id' => 'string', 'tenant_id' => 'string',
        'email_verified_at' => 'datetime', 'password' => 'hashed',
    ];
}
```

---

## 5. Cómo Trabajar con Agentes IA

### 5.1 Tu rol como desarrollador (John)

TÚ describes QUÉ quieres lograr (lenguaje natural)
↓
AGENTE produce FEATURE_SPEC.md
↓
TÚ escribes APROBADO (activa GATE 1)
↓
AGENTE propone Schema SQL
↓
TÚ escribes APROBADO (activa migración)
↓
TÚ escribes APROBADO (activa ejecución migrate)
↓
AGENTE ejecuta en orden estricto:

  Tests (primero — TDD)
  Docs actualizadas
  Código hasta que tests pasen
  Reporte de cobertura
  FEATURE_SPEC actualizado
↓
TÚ pruebas en el navegador

**No necesitas saber la sintaxis exacta de Laravel.**
Dime qué necesitas, yo determino si es tabla nueva o existente,
genero la migración, modelo, service y resource.

### 5.2 Cómo pedir un feature nuevo

**Paso 1:** Me dices en lenguaje natural qué necesitas.

**Paso 2:** Yo produzco un `FEATURE_SPEC.md` con:

```markdown
## Feature: Órdenes de Trabajo

### Descripción
Módulo para gestionar órdenes de trabajo asignadas a assets.

### Tablas
- `work_orders` (nueva) con FK a `assets`

### Campos
- asset_id, title, description, assigned_to, status, priority, due_date

### Seguridad
- RLS en tabla work_orders
- Policy de Laravel: solo admin y editor pueden crear

### Frontend
- Filament Resource WorkOrderResource
```

**Paso 3:** Revisas el spec y dices **"APROBADO"** (solo esa palabra activa la ejecución).

**Paso 4:** Ejecuto en orden estricto: Tests → Docs → Code → Report → Update. No empiezo a codificar sin tests escritos primero.

### 5.3 Reglas que los agentes siguen (y tú debes conocer)

| Regla | ¿Qué significa para ti? |
|---|---|
| **Nada de Prisma** | Si ves a alguien mencionar Prisma, está violando las reglas. El stack es Eloquent. |
| **Nada de `SELECT *`** | Siempre se usan columnas explícitas: `Asset::select(['id', 'name'])`. |
| **No ejecuto migraciones sin tu "APROBADO"** | Tú tienes el control. Revisas el spec primero. |
| **No escribo código en el chat** | Edito los archivos directamente. Tú ves los cambios en tu editor. |
| **Siempre reporto qué cambié** | Al final de cada tarea, una tabla con archivos modificados. |

### 5.4 Qué decir y qué NO

```
✅ "Crea un módulo de ordenes de trabajo"
✅ "Agrega un campo 'due_date' a la tabla work_orders"
✅ "Haz que solo admin pueda eliminar órdenes"
✅ "Genera el Filament Resource para WorkOrder"

❌ "pon en el chat el código de la migración que necesito"  → Yo edito el archivo, no lo pego
❌ "ejecuta php artisan migrate sin mostrarme"              → Sin APROBADO no ejecuto
❌ "usa Prisma para esto"                                   → Prisma no existe en este stack
❌ "haz un SELECT * de assets"                              → Siempre columnas explícitas
```

---

## 6. Lo Implementado y Siguientes Pasos

### ✅ Ya implementado:

| Feature | Estado | Notas |
|---------|--------|-------|
| Filament Panel | ✅ | AdminPanelProvider, login en `/admin`, Filament 5 Schema API, SetTenantContext middleware |
| Comando `tenant:create` | ✅ | Crea tenant + admin + ejecuta RolePermissionSeeder + asigna rol owner |
| Spatie Permission (Opción B) | ✅ | `teams=false`, RLS manual, custom Role/Permission models, 5 tablas con tenant_id, 20 permisos |
| Work Orders feature | ✅ | Migración + modelos + WorkOrderService + policy + Filament Resource + 4 tests |
| Transactions feature | ✅ | Migración 2 tablas + Transaction + TransactionItem + TransactionService (contador atómico) + Policy + Filament Resource + 9 tests |
| Auth system (registro público) | ✅ | RegisterController + RegisterService (crea tenant + defaults por industria), validación estricta, rate limiting 10/hora |
| Auth (password reset) | ✅ | ForgotPasswordController + ResetPasswordController + custom notification + Blade views, token 30 min |
| Auth (API tokens) | ✅ | ApiTokenController via Sanctum, crear/revocar/listar, tokenable_id UUID |
| Registro con defaults por industria | ✅ | 5 industrias (config/industry-defaults.php): location + categories + items + contacts + módulos activados automáticamente |
| Catálogo de módulos | ✅ | modules_catalog (tabla global) + tenant_modules (por tenant) + ModulesCatalogSeeder (5 módulos) |
| Categories | ✅ | Categorías por tenant con RLS + Locations con is_main |
| Factories | ✅ | 7 factories (Tenant, User, Asset, Item, Contact, WorkOrder, WorkOrderItem) |
| Seeders | ✅ | DatabaseSeeder + RolePermissionSeeder (4 roles, 20 permisos) + ModulesCatalogSeeder (5 módulos) |
| UX/UI Resources | ✅ | 6 Filament Resources (Asset, Contact, Item, Location, Transaction, WorkOrder), Grid anidado eliminado, headerActions duplicados eliminados |
| Tests | ✅ | 60 tests (165 assertions) — Auth (28), Spatie (12), Transactions (9), Onboarding (6), WorkOrders (5) |

### 🔜 Pendiente:

| Feature | Prioridad | Descripción |
|---------|-----------|-------------|
| Deploy con `app_user` no-superuser | Media | Rol BD no superuser para que RLS sea efectivo en producción |
| Wizard onboarding post-registro | Baja | Paso a paso para completar perfil del tenant después del registro |
| Reportes / Dashboard | Baja | Panel resumen con KPIs del tenant |

---

## 7. Apéndices

### A. Árbol de decisiones para nuevas funcionalidades

```
¿Necesito una nueva entidad?
    │
    ├─ ¿Ya existe una tabla con nombre genérico equivalente?
    │   Ej: ¿es un "vehículo"? → Asset con asset_type='vehicle'
    │   Ej: ¿es un "cliente"?  → Contact con contact_type='client'
    │   │
    │   ├─ SÍ → Usar tabla existente, agregar tipo si es necesario
    │   │
    │   └─ NO → Crear nueva tabla con tenant_id + RLS + UUID
    │
    └─ ¿La entidad aplica a cualquier industria?
        │
        ├─ SÍ → Usar nomenclatura genérica
        │
        └─ NO → Usar nombre específico pero justificar
```

### B. Checklist de verificación post-migración

```sql
-- 1. ¿Todas las tablas con tenant_id tienen RLS?
SELECT tablename FROM pg_tables
WHERE schemaname = 'public'
  AND tablename NOT IN (
    SELECT tablename FROM pg_policies WHERE schemaname = 'public'
  )
  AND tablename NOT IN ('tenants', 'migrations');

-- 2. ¿Todas tienen FORCE RLS?
SELECT relname FROM pg_class
WHERE relrowsecurity = true AND relforcerowsecurity = false AND relkind = 'r';

-- 3. ¿Todas tienen índice en tenant_id?
SELECT t.tablename FROM pg_tables t
WHERE schemaname = 'public'
  AND EXISTS (
    SELECT 1 FROM information_schema.columns c
    WHERE c.table_name = t.tablename AND c.column_name = 'tenant_id'
  )
  AND NOT EXISTS (
    SELECT 1 FROM pg_indexes i
    WHERE i.tablename = t.tablename AND i.indexdef LIKE '%tenant_id%'
  );
```

### C. Comandos útiles

```bash
# Migraciones
php artisan migrate                        # Ejecutar pendientes
php artisan migrate:fresh                  # Reconstruir desde cero
php artisan migrate:rollback               # Revertir último lote
php artisan migrate:status                 # Ver estado

# Filament
php artisan filament:install --panels      # Instalar Filament Panel
php artisan make:filament-resource Asset   # Crear Resource

# Artisan custom
php artisan tenant:create "Nombre" slug    # Crear tenant

# Testing
php artisan test --filter=TenantIsolation  # Tests de aislamiento
php artisan test                            # Todos los tests
```

---

*Jaosoft Engineering Standards v1.0 — ProyectDashboard*
*Documento generado para aprendizaje del equipo. No modificar sin pasar por SDLC.*
