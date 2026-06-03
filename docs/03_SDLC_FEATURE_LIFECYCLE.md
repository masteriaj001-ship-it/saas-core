03 — Ciclo de Vida de un Feature (SDLC)
ProyectDashboard · Jaosoft Engineering Standards v1.0

Visión General
Todo feature en ProyectDashboard pasa por 5 fases obligatorias antes de considerarse completo. No existe el "lo implementé directamente" sin análisis previo.
FASE 1: Análisis y Diseño
         │
         ▼
FASE 2: Diseño de Datos (Schema First)
         │
         ▼
FASE 3: Implementación (Backend → Frontend)
         │
         ▼
FASE 4: Verificación de Seguridad
         │
         ▼
FASE 5: Documentación y Cierre

FASE 1 — Análisis y Diseño
Entregable: FEATURE_SPEC.md
Antes de escribir una sola línea de código, el agente o desarrollador debe producir un documento con:
markdown## Feature: [nombre del feature]

### Descripción
[Qué hace este feature en 2-3 oraciones]

### Módulo
[Ej: Assets, Contacts, Documents, Members]

### Casos de Uso
- [ ] UC-01: [actor] puede [acción] cuando [condición]
- [ ] UC-02: ...

### Restricciones de Negocio
- Regla 1: [Ej: Un Asset solo puede tener un status activo simultáneamente]
- Regla 2: ...

### Impacto en Datos
- Tablas nuevas: [listar]
- Tablas modificadas: [listar con los cambios]
- Índices necesarios: [listar]

### Impacto en Seguridad
- ¿Requiere nuevas políticas RLS? [sí/no + detalle]
- ¿Expone datos cross-tenant en algún punto? [sí/no + análisis]

### Dependencias
- Features previos requeridos: [listar]
- Paquetes externos nuevos: [listar o "ninguno"]

### Documentación a Actualizar
- [ ] `docs/PROJECT_STATE.md` — handoff log + secciones afectadas

#### Checklist de Aprobación Fase 1

 El nombre del feature usa nomenclatura Zero Redundancy
 No duplica lógica de otro módulo existente
 El modelo de datos es agnóstico de industria
 No requiere Prisma ni ORMs alternativos
 Revisado por: Gemini (revisor de producto/código)


FASE 2 — Diseño de Datos (Schema First)
Regla absoluta: El esquema de base de datos se diseña y revisa antes de escribir el modelo Eloquent.
Proceso

Borrador SQL — escribir el CREATE TABLE completo con:

tenant_id UUID NOT NULL como segunda columna
Todos los índices previstos
Constraints de unicidad por tenant (UNIQUE (tenant_id, campo))


Revisión de normalización — verificar que no hay redundancia con tablas existentes
Aprobación del borrador — el humano (John) debe escribir APROBADO explícitamente
Generación de migración — Antigravity genera el archivo de migración siguiendo la plantilla de 02_ELOQUENT_AND_MIGRATIONS.md
Ejecución via Qwen — Qwen ejecuta php artisan migrate y reporta resultado

⚠️ REGLA ABSOLUTA #0
Ningún agente ejecuta una migración sin aprobación explícita.
La palabra de activación es "APROBADO" escrita por John.
Ninguna paráfrasis, ningún "entendido", ningún "procede" es válido.
Solo "APROBADO".
Esta regla existe porque Antigravity ejecutó migraciones no aprobadas en el pasado,
causando trabajo de remediación significativo.
Índices Mínimos Obligatorios por Tabla
Tipo de índiceObligatorioEjemplotenant_id simple✅ SiempreINDEX (tenant_id)(tenant_id, status)✅ Si tiene statusPara filtros frecuentes(tenant_id, created_at)✅ Si se ordena por fechaPaginación(tenant_id, campo_unique)✅ Si tiene unicidad por tenantUNIQUE (tenant_id, sku)GIN en JSONB⚠️ Solo si se consulta metadataUSING GIN (metadata)

FASE 3 — Implementación
Orden de Implementación (Backend First)
1. Migración + RLS
       ↓
2. Modelo Eloquent (extiende TenantModel)
       ↓
3. Policy de Laravel (autorización por rol)
       ↓
4. Form Request (validación)
       ↓
5. Service Class (lógica de negocio)
       ↓
6. Controller / Action
       ↓
7. Filament Resource (panel admin)
       ↓
8. Tests
Estructura de Directorios
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── {Module}/
│   │           └── {Model}Controller.php
│   └── Requests/
│       └── {Module}/
│           ├── Create{Model}Request.php
│           └── Update{Model}Request.php
├── Models/
│   └── {Model}.php
├── Policies/
│   └── {Model}Policy.php
├── Services/
│   ├── Tenant/
│   │   └── TenantManager.php
│   └── {Module}/
│       └── {Model}Service.php
└── Filament/
    └── Resources/
        └── {Model}Resource/
            ├── {Model}Resource.php
            └── Pages/
                ├── List{Model}s.php
                ├── Create{Model}.php
                └── Edit{Model}.php
Plantilla de Service Class
php<?php

declare(strict_types=1);

namespace App\Services\Assets;

use App\Modules\Talleres\Models\Asset;
use App\Http\Requests\Assets\CreateAssetRequest;
use App\Http\Requests\Assets\UpdateAssetRequest;
use Illuminate\Pagination\LengthAwarePaginator;

final class AssetService
{
    /**
     * Listar assets del tenant activo con filtros opcionales.
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Asset::select(['id', 'name', 'code', 'asset_type', 'status', 'created_at'])
            ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['asset_type'])) {
            $query->where('asset_type', $filters['asset_type']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ilike', "%{$filters['search']}%")
                  ->orWhere('code', 'ilike', "%{$filters['search']}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Crear asset — tenant_id lo inyecta el trait automáticamente.
     */
    public function create(CreateAssetRequest $request): Asset
    {
        return Asset::create(
            $request->only(['name', 'code', 'asset_type', 'status', 'metadata', 'acquired_at'])
        );
    }

    /**
     * Actualizar — findOrFail ya aplica tenant scope.
     */
    public function update(string $id, UpdateAssetRequest $request): Asset
    {
        $asset = Asset::findOrFail($id);
        $asset->update($request->only(['name', 'code', 'status', 'metadata']));
        return $asset->fresh();
    }

    /**
     * Soft delete — preserva para auditoría.
     */
    public function delete(string $id): void
    {
        Asset::findOrFail($id)->delete();
    }
}
Form Request con Validación UUID
php<?php

declare(strict_types=1);

namespace App\Http\Requests\Assets;

use Illuminate\Foundation\Http\FormRequest;

class CreateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Modules\Talleres\Models\Asset::class);
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'code'       => ['nullable', 'string', 'max:100'],
            'asset_type' => ['required', 'string', 'in:vehicle,machinery,equipment,property'],
            'status'     => ['sometimes', 'string', 'in:active,inactive,maintenance,disposed'],
            'metadata'   => ['sometimes', 'array'],
            'acquired_at'=> ['sometimes', 'nullable', 'date'],
        ];
    }
}

FASE 4 — Verificación de Seguridad
Checklist Obligatorio Antes de Merge/Deploy
bash# 1. Verificar que todas las tablas nuevas tienen RLS activo
# (ver query en 01_MULTI_TENANT_FLOW.md, sección 6)

# 2. Verificar que no hay rutas expuestas sin middleware de tenant
php artisan route:list --columns=uri,middleware | grep -v "SetTenantContext"
# → Si aparece una ruta de negocio sin ese middleware, es un bug.

# 3. Verificar que no hay $guarded = [] en ningún modelo
grep -r "guarded = \[\]" app/Models/
# → No debe retornar nada.

# 4. Verificar que no hay SELECT * en queries
grep -r "->all()" app/
grep -r "->get()" app/ | grep -v "->select("
# → Revisar manualmente los resultados.

# 5. Test de aislamiento cross-tenant
php artisan test --filter=TenantIsolationTest
Test de Aislamiento (Obligatorio por Módulo)
php<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_a_cannot_see_tenant_b_assets(): void
    {
        // Crear dos tenants con sus assets
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->for($tenantA)->create();
        $userB = User::factory()->for($tenantB)->create();

        Asset::factory()->for($tenantA)->count(3)->create();
        Asset::factory()->for($tenantB)->count(2)->create();

        // Autenticar como usuario del tenant A
        $this->actingAs($userA);
        app(TenantManager::class)->setTenantContext($tenantA->id);

        // Solo debe ver los 3 assets del tenant A
        $this->assertEquals(3, Asset::count());

        // Autenticar como usuario del tenant B
        $this->actingAs($userB);
        app(TenantManager::class)->setTenantContext($tenantB->id);

        // Solo debe ver los 2 assets del tenant B
        $this->assertEquals(2, Asset::count());
    }
}

FASE 5 — Documentación y Cierre
Actualizar docs/PROJECT_STATE.md
markdown## [FECHA] — Feature: {nombre}

### Añadido
- Tabla `{nombre}` con RLS habilitado
- Modelo `{Model}` con trait BelongsToTenant
- Filament Resource para gestión en panel admin
- 4 políticas RLS: SELECT, INSERT, UPDATE, DELETE

### Seguridad
- Verificación cross-tenant: ✅ Pasó TenantIsolationTest
- RLS activo: ✅ Confirmado con query de auditoría
- Sin rutas expuestas: ✅ Verificado con route:list

### Archivos Modificados
- `database/migrations/XXXX_create_{tabla}_table.php` — nueva
- `app/Models/{Model}.php` — nuevo
- `app/Services/{Module}/{Model}Service.php` — nuevo
- `app/Filament/Resources/{Model}Resource.php` — nuevo

Ver también: docs/WORKFLOW.md para el protocolo de operación con agentes