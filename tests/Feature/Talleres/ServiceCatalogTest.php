<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\VehicleTypeEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ServiceCatalog;
use App\Services\TenantManager;
use App\Services\TenantTemplateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_can_create_service_catalog(): void
    {
        $catalog = ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cambio de aceite',
            'description' => 'Cambio de aceite y filtro',
            'base_price' => 150.00,
            'estimated_minutes' => 60,
        ]);

        $this->assertDatabaseHas('service_catalogs', [
            'id' => $catalog->id,
            'name' => 'Cambio de aceite',
            'base_price' => 150.00,
        ]);
    }

    public function test_service_catalog_belongs_to_tenant(): void
    {
        $tenantB = Tenant::factory()->create();

        ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alineación',
            'base_price' => 80.00,
        ]);

        app(TenantManager::class)->setTenantContext($tenantB->id);

        $catalogs = ServiceCatalog::where('name', 'Alineación')->get();

        $this->assertCount(0, $catalogs);
    }

    public function test_service_catalog_requires_name_and_price(): void
    {
        $this->expectException(QueryException::class);

        ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => null,
            'base_price' => null,
        ]);
    }

    public function test_vehicle_type_enum_has_correct_values(): void
    {
        $this->assertEquals('sedan', VehicleTypeEnum::Sedan->value);
        $this->assertEquals('motorcycle', VehicleTypeEnum::Motorcycle->value);
        $this->assertEquals('pickup_truck', VehicleTypeEnum::PickupTruck->value);
    }

    public function test_mechanic_template_seeds_service_catalogs(): void
    {
        $seeder = app(TenantTemplateSeeder::class);

        $seeder->seed($this->tenant, 'mechanic');

        $catalogs = ServiceCatalog::where('tenant_id', $this->tenant->id)->get();

        $this->assertCount(5, $catalogs);
        $this->assertNotNull($catalogs->firstWhere('name', 'Cambio de aceite y filtro'));
        $this->assertNotNull($catalogs->firstWhere('name', 'Revisión de frenos'));
        $this->assertNotNull($catalogs->firstWhere('name', 'Diagnóstico electrónico'));
        $this->assertNotNull($catalogs->firstWhere('name', 'Alineación y balanceo'));
        $this->assertNotNull($catalogs->firstWhere('name', 'Sincronización de motor'));
    }
}
