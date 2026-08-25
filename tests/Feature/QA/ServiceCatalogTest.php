<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ServiceCatalog;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_can_create_service_catalog_entry(): void
    {
        $service = ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cambio de aceite y filtro',
            'description' => 'Incluye aceite sintético 5W30 y filtro original',
            'base_price' => 45000,
            'estimated_minutes' => 30,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('service_catalogs', [
            'id' => $service->id,
            'name' => 'Cambio de aceite y filtro',
            'base_price' => 45000,
            'is_active' => true,
        ]);
    }

    public function test_service_catalog_has_required_fields(): void
    {
        $service = ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Servicio mínimo',
            'base_price' => 10000,
            'is_active' => true,
        ]);

        $this->assertNotNull($service->name);
        $this->assertNotNull($service->base_price);
        $this->assertIsBool($service->is_active);
    }

    public function test_service_catalog_base_price_is_decimal(): void
    {
        $service = ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Diagnóstico',
            'base_price' => 25000.50,
            'is_active' => true,
        ]);

        $this->assertEquals('25000.50', $service->fresh()->base_price);
    }

    public function test_service_catalog_can_be_deactivated(): void
    {
        $service = ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Servicio descontinuado',
            'base_price' => 20000,
            'is_active' => true,
        ]);

        $service->update(['is_active' => false]);

        $this->assertFalse($service->fresh()->is_active);
    }

    public function test_service_catalog_estimated_minutes(): void
    {
        $service = ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Service with estimate',
            'base_price' => 30000,
            'estimated_minutes' => 90,
            'is_active' => true,
        ]);

        $this->assertEquals(90, $service->fresh()->estimated_minutes);
    }

    public function test_multiple_services_can_exist(): void
    {
        ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cambio de aceite',
            'base_price' => 45000,
            'is_active' => true,
        ]);

        ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Revisión de frenos',
            'base_price' => 35000,
            'is_active' => true,
        ]);

        ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Diagnóstico electrónico',
            'base_price' => 25000,
            'is_active' => true,
        ]);

        $this->assertEquals(3, ServiceCatalog::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_service_catalog_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        ServiceCatalog::create([
            'name' => 'Service A',
            'base_price' => 10000,
            'is_active' => true,
        ]);

        DB::table('service_catalogs')->forceCreate([
            'id' => fake()->uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Service B',
            'base_price' => 20000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertEquals(1, ServiceCatalog::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(1, DB::table('service_catalogs')->where('tenant_id', $otherTenant->id)->count());
    }
}
