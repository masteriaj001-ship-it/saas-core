02 eloquent and migrations · MDCopiar02 — Eloquent, Modelos y Migraciones
ProyectDashboard · Jaosoft Engineering Standards v1.0

1. El Trait BelongsToTenant — Segunda Línea de Defensa
¿Por qué existe si ya tenemos RLS?
RLS es indestructible cuando la conexión va por el middleware. Pero existen casos donde un query puede escapar:

DB::unprepared() con SQL crudo
Conexiones directas en scripts Artisan sin middleware HTTP
Seeds y factories en tests mal configurados
Jobs que arrancan con la conexión en estado limpio (sin tenant context)

El trait BelongsToTenant aplica un global scope de Eloquent que agrega automáticamente WHERE tenant_id = ? a todos los queries del modelo. Doble cerrojo.
Implementación
php<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

trait BelongsToTenant
{
    /**
     * Boot del trait: registra el global scope y el filling
     * automático de tenant_id en creación.
     */
    public static function bootBelongsToTenant(): void
    {
        // Global scope: filtra siempre por tenant activo
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantManager = app(TenantManager::class);

            if (!$tenantManager->hasContext()) {
                // En contextos de consola/tests sin tenant → no filtrar
                // pero loguear para auditoría
                if (app()->runningInConsole()) {
                    return;
                }
                throw new RuntimeException(
                    'BelongsToTenant: No tenant context. ' .
                    'Ensure SetTenantContext middleware is active.'
                );
            }

            $builder->where(
                $builder->getModel()->getTable() . '.tenant_id',
                $tenantManager->getCurrentTenantId()
            );
        });

        // Auto-fill tenant_id en creación
        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $tenantManager = app(TenantManager::class);

                if (!$tenantManager->hasContext()) {
                    throw new RuntimeException(
                        'Cannot create ' . static::class . ' without tenant context.'
                    );
                }

                $model->tenant_id = $tenantManager->getCurrentTenantId();
            }
        });
    }

    /**
     * Relación al modelo Tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope para queries administrativos cross-tenant.
     * USO EXCLUSIVO: panel superadmin de Jaosoft.
     * NUNCA exponer en rutas públicas.
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}

2. Modelo Base Tenant-Aware
Todos los modelos de negocio deben extender esta clase base o usar el trait directamente:
php<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class TenantModel extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    /**
     * Todos los modelos de negocio usan UUID v4 como PK.
     * HasUuids lo gestiona automáticamente vía Str::orderedUuid().
     *
     * Si necesitas gen_random_uuid() de PG en lugar de UUID de PHP,
     * sobreescribe newUniqueId() en el modelo hijo.
     */

    /**
     * Campos que NUNCA deben ser mass-assignable en ningún modelo.
     */
    protected $guarded = [
        'id',
        'tenant_id',    // Siempre se inyecta vía trait, nunca vía input
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Cast por defecto para todos los modelos.
     */
    protected function casts(): array
    {
        return [
            'id'         => 'string',
            'tenant_id'  => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}

3. Modelos Canónicos — Nomenclatura Zero Redundancy
Asset (vehículos, maquinaria, equipos, propiedades...)
php<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends TenantModel
{
    protected $fillable = [
        'name',
        'code',
        'asset_type',       // enum: 'phones', 'computers', 'vehicles'
        'category_id',
        'status',           // enum: 'active', 'inactive', 'maintenance', 'disposed'
        'acquired_at',
        'disposed_at',
        'metadata',         // JSONB: campos específicos por tipo/industria
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata'    => 'array',
            'acquired_at' => 'date',
            'disposed_at' => 'date',
        ]);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'entity_id')
            ->where('entity_type', self::class);
    }
}
Contact (clientes, proveedores, empleados como contacto externo...)
php<?php

declare(strict_types=1);

namespace App\Models;

class Contact extends TenantModel
{
    protected $fillable = [
        'name',
        'contact_type',     // enum: 'client', 'supplier', 'partner', 'other'
        'tax_id',           // NIT, RUT, CUIT según país
        'email',
        'phone',
        'address',
        'metadata',         // JSONB
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'metadata' => 'array',
        ]);
    }
}
Item (repuestos, productos, servicios, SKUs...)
php<?php

declare(strict_types=1);

namespace App\Models;

class Item extends TenantModel
{
    protected $fillable = [
        'name',
        'sku',              // Único POR tenant (no global)
        'item_type',        // enum: 'product', 'service', 'spare_part', 'raw_material'
        'unit',             // enum: 'unit', 'kg', 'liter', 'hour', 'meter'
        'price',
        'cost',
        'stock',
        'min_stock',
        'metadata',         // JSONB
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'price'    => 'decimal:2',
            'cost'     => 'decimal:2',
            'stock'    => 'integer',
            'metadata' => 'array',
        ]);
    }
}

4. Estructura de Migraciones
Reglas Absolutas

Toda tabla de negocio tiene tenant_id UUID NOT NULL
El índice compuesto (tenant_id, id) es obligatorio
RLS se habilita en la misma migración que crea la tabla
gen_random_uuid() de PG como default en PK, nunca string PHP

Plantilla de Migración Estándar
php<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            // PK: UUID generado por PostgreSQL
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            // Tenant isolation — OBLIGATORIO, primera columna de negocio
            $table->foreignUuid('tenant_id')
                  ->constrained('tenants')
                  ->cascadeOnDelete();

            // Campos de negocio
            $table->string('name', 255);
            $table->string('code', 100)->nullable();
            $table->string('asset_type', 50);
            $table->string('status', 50)->default('active');
            $table->jsonb('metadata')->default('{}');
            $table->date('acquired_at')->nullable();
            $table->date('disposed_at')->nullable();

            // Timestamps + soft delete
            $table->timestamps();
            $table->softDeletes();

            // Índices OBLIGATORIOS
            $table->index('tenant_id');                     // RLS performance
            $table->index(['tenant_id', 'status']);         // Queries frecuentes
            $table->index(['tenant_id', 'asset_type']);     // Queries frecuentes
            $table->unique(['tenant_id', 'code']);          // Unicidad por tenant
        });

        // RLS — SIEMPRE en la misma migración
        DB::unprepared("
            ALTER TABLE assets ENABLE ROW LEVEL SECURITY;
            ALTER TABLE assets FORCE ROW LEVEL SECURITY;

            CREATE POLICY \"assets_tenant_isolation_select\"
                ON assets FOR SELECT
                USING (tenant_id = public.current_tenant_id());

            CREATE POLICY \"assets_tenant_isolation_insert\"
                ON assets FOR INSERT
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY \"assets_tenant_isolation_update\"
                ON assets FOR UPDATE
                USING (tenant_id = public.current_tenant_id())
                WITH CHECK (tenant_id = public.current_tenant_id());

            CREATE POLICY \"assets_tenant_isolation_delete\"
                ON assets FOR DELETE
                USING (tenant_id = public.current_tenant_id());
        ");
    }

    public function down(): void
    {
        DB::unprepared("
            DROP POLICY IF EXISTS \"assets_tenant_isolation_select\" ON assets;
            DROP POLICY IF EXISTS \"assets_tenant_isolation_insert\" ON assets;
            DROP POLICY IF EXISTS \"assets_tenant_isolation_update\" ON assets;
            DROP POLICY IF EXISTS \"assets_tenant_isolation_delete\" ON assets;
        ");

        Schema::dropIfExists('assets');
    }
};

5. Reglas de Eloquent — Prohibiciones y Obligaciones
❌ PROHIBIDO
php// PROHIBIDO: SELECT * — siempre especificar columnas
Asset::all();
Asset::get();
DB::table('assets')->get();

// PROHIBIDO: Prisma, Doctrine, o cualquier ORM alternativo
// (violación de gobernanza)

// PROHIBIDO: mass assignment sin $fillable definido
Asset::create($request->all());  // ← nunca

// PROHIBIDO: queries sin tenant scope en rutas no-admin
Asset::withoutGlobalScope('tenant')->get();  // ← solo superadmin

// PROHIBIDO: eager loading sin límite en colecciones grandes
$assets = Asset::with('items', 'documents')->get();  // ← paginar primero
✅ OBLIGATORIO
php// Siempre paginar
Asset::where('status', 'active')
     ->select(['id', 'name', 'code', 'status', 'asset_type'])
     ->latest()
     ->paginate(25);

// Eager loading con restricción de columnas
Asset::with(['items:id,asset_id,name,sku,stock'])
     ->select(['id', 'name', 'code'])
     ->paginate(25);

// Creación siempre con fillable explícito
Asset::create($request->only(['name', 'code', 'asset_type', 'metadata']));

// Updates con select previo (evita UPDATE masivos)
$asset = Asset::findOrFail($id);
$asset->update($request->only(['name', 'status']));

// Queries de conteo sin cargar modelos
Asset::where('status', 'active')->count();

// Chunks para procesamiento masivo
Asset::where('status', 'active')
     ->chunk(200, function ($assets) {
         // procesar lote
     });

6. Convenciones de Campos JSONB
Los campos metadata en JSONB permiten extensión sin migraciones por industria:
php// Ejemplo: metadata para asset tipo 'vehicle'
[
    'brand'       => 'Toyota',
    'model'       => 'Hilux',
    'year'        => 2020,
    'plate'       => 'ABC-123',
    'vin'         => 'JTFBT00P...',
    'fuel_type'   => 'diesel',
    'mileage'     => 45000,
]

// Ejemplo: metadata para asset tipo 'machinery'
[
    'brand'       => 'Caterpillar',
    'model'       => '320D',
    'hours'       => 1200,
    'location'    => 'Planta Norte',
]
Regla: Nunca normalizar en columnas separadas lo que varía por asset_type. Usar metadata JSONB y crear índices GIN si se consulta frecuentemente:
sqlCREATE INDEX CONCURRENTLY idx_assets_metadata_gin
ON assets USING GIN (metadata);
Nota: CREATE INDEX CONCURRENTLY no puede ejecutarse dentro de una transacción. Usar en migración separada o via Artisan custom command.

Ver también: 01_MULTI_TENANT_FLOW.md para contexto de RLS