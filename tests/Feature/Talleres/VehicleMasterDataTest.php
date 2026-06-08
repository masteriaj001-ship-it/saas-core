<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\FuelTypeEnum;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\Asset;
use App\Services\TenantManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleMasterDataTest extends TestCase
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

    public function test_can_create_asset_with_all_vehicle_fields(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $asset = Asset::factory()->vehicle()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Toyota Hilux 2024',
            'version' => 'SRX 4x4',
            'engine_number' => '2TR-123456',
            'current_mileage' => 15000,
            'fuel_type' => FuelTypeEnum::Diesel,
            'color' => 'Blanco',
            'owner_contact_id' => $owner->id,
        ]);

        $fresh = $asset->fresh();

        $this->assertEquals('Toyota Hilux 2024', $fresh->name);
        $this->assertEquals('vehicle', $fresh->asset_type);
        $this->assertEquals('SRX 4x4', $fresh->version);
        $this->assertEquals('2TR-123456', $fresh->engine_number);
        $this->assertEquals(15000, $fresh->current_mileage);
        $this->assertTrue($fresh->fuel_type === FuelTypeEnum::Diesel);
        $this->assertEquals('Blanco', $fresh->color);
        $this->assertEquals($owner->id, $fresh->owner_contact_id);
    }

    public function test_owner_relationship_returns_contact(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $asset = Asset::factory()->vehicle()->create([
            'tenant_id' => $this->tenant->id,
            'owner_contact_id' => $owner->id,
        ]);

        $this->assertInstanceOf(Contact::class, $asset->owner);
        $this->assertEquals($owner->id, $asset->owner->id);
        $this->assertEquals($owner->name, $asset->owner->name);
    }

    public function test_fuel_type_enum_values(): void
    {
        $this->assertTrue(FuelTypeEnum::tryFrom('gasoline') !== null);
        $this->assertTrue(FuelTypeEnum::tryFrom('diesel') !== null);
        $this->assertTrue(FuelTypeEnum::tryFrom('hybrid') !== null);
        $this->assertTrue(FuelTypeEnum::tryFrom('electric') !== null);
        $this->assertTrue(FuelTypeEnum::tryFrom('gas') !== null);
        $this->assertTrue(FuelTypeEnum::tryFrom('other') !== null);

        $this->assertEquals('Gasolina', FuelTypeEnum::Gasoline->getLabel());
        $this->assertEquals('Diésel', FuelTypeEnum::Diesel->getLabel());
        $this->assertEquals('Híbrido', FuelTypeEnum::Hybrid->getLabel());
        $this->assertEquals('Eléctrico', FuelTypeEnum::Electric->getLabel());
        $this->assertEquals('Gas', FuelTypeEnum::Gas->getLabel());
        $this->assertEquals('Otro', FuelTypeEnum::Other->getLabel());
    }

    public function test_is_vehicle_helper(): void
    {
        $vehicle = Asset::factory()->vehicle()->create(['tenant_id' => $this->tenant->id]);
        $this->assertTrue($vehicle->isVehicle());

        $equipment = Asset::factory()->equipment()->create(['tenant_id' => $this->tenant->id]);
        $this->assertFalse($equipment->isVehicle());
    }

    public function test_vehicle_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $owner = Contact::factory()->for($otherTenant)->client()->create();

        $vehicle = Asset::factory()->vehicle()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Vehicle De Otro Tenant',
            'owner_contact_id' => $owner->id,
        ]);

        // Desde el tenant original, no debe ver el vehículo del otro
        $visible = Asset::where('id', $vehicle->id)->first();
        $this->assertNull($visible);

        // Sin scope, sí se ve
        $withoutScope = Asset::withoutTenantScope()->find($vehicle->id);
        $this->assertNotNull($withoutScope);
    }

    public function test_owner_contact_id_fk_set_null_on_contact_delete(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $asset = Asset::factory()->vehicle()->create([
            'tenant_id' => $this->tenant->id,
            'owner_contact_id' => $owner->id,
        ]);

        $owner->forceDelete();

        $fresh = $asset->fresh();
        $this->assertNull($fresh->owner_contact_id);
    }

    public function test_current_mileage_must_be_non_negative(): void
    {
        $this->expectException(QueryException::class);

        Asset::factory()->vehicle()->create([
            'tenant_id' => $this->tenant->id,
            'current_mileage' => -1,
        ]);
    }

    public function test_year_must_be_within_valid_range(): void
    {
        $this->expectException(QueryException::class);

        Asset::factory()->vehicle()->create([
            'tenant_id' => $this->tenant->id,
            'year' => 1899,
        ]);
    }

    public function test_version_and_engine_number_are_nullable(): void
    {
        $asset = Asset::factory()->vehicle()->create([
            'tenant_id' => $this->tenant->id,
            'version' => null,
            'engine_number' => null,
        ]);

        $this->assertNull($asset->version);
        $this->assertNull($asset->engine_number);
    }

    public function test_can_create_vehicle_inline_from_work_order(): void
    {
        // Simula el callback createOptionUsing del WorkOrderResource
        $asset = Asset::create([
            'name' => 'WorkOrder Inline Vehicle',
            'plate' => 'INL-001',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2024,
            'version' => 'Touring',
            'vin' => '2HGFC2F93LH567890',
            'engine_number' => 'R18-98765',
            'current_mileage' => 5000,
            'fuel_type' => FuelTypeEnum::Gasoline,
            'color' => 'Gris',
            'vehicle_type' => 'sedan',
            'asset_type' => 'vehicle',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'name' => 'WorkOrder Inline Vehicle',
            'plate' => 'INL-001',
            'asset_type' => 'vehicle',
            'status' => 'active',
            'version' => 'Touring',
            'engine_number' => 'R18-98765',
            'current_mileage' => 5000,
            'color' => 'Gris',
        ]);

        $this->assertTrue($asset->isVehicle());
    }
}
