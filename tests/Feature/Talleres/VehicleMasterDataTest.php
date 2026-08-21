<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\FuelTypeEnum;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Services\TenantManager;
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

    public function test_can_create_client_vehicle_with_all_fields(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_contact_id' => $owner->id,
            'plate' => 'ABC-123',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'version' => 'SRX 4x4',
            'vin' => '2HGFC2F93LH567890',
            'engine_number' => '2TR-123456',
            'current_mileage' => 15000,
            'fuel_type' => FuelTypeEnum::Diesel,
            'color' => 'Blanco',
            'vehicle_type' => 'pickup_truck',
        ]);

        $fresh = $vehicle->fresh();

        $this->assertEquals('ABC-123', $fresh->plate);
        $this->assertEquals('Toyota', $fresh->brand);
        $this->assertEquals('Hilux', $fresh->model);
        $this->assertEquals(2024, $fresh->year);
        $this->assertEquals('SRX 4x4', $fresh->version);
        $this->assertEquals('2HGFC2F93LH567890', $fresh->vin);
        $this->assertEquals('2TR-123456', $fresh->engine_number);
        $this->assertEquals(15000, $fresh->current_mileage);
        $this->assertTrue($fresh->fuel_type === FuelTypeEnum::Diesel);
        $this->assertEquals('Blanco', $fresh->color);
        $this->assertEquals($owner->id, $fresh->owner_contact_id);
    }

    public function test_owner_relationship_returns_contact(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_contact_id' => $owner->id,
        ]);

        $this->assertInstanceOf(Contact::class, $vehicle->owner);
        $this->assertEquals($owner->id, $vehicle->owner->id);
        $this->assertEquals($owner->name, $vehicle->owner->name);
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

    public function test_client_vehicle_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $owner = Contact::factory()->for($otherTenant)->client()->create();

        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'XYZ-999',
            'owner_contact_id' => $owner->id,
        ]);

        // Desde el tenant original, no debe ver el vehículo del otro
        $visible = ClientVehicle::where('id', $vehicle->id)->first();
        $this->assertNull($visible);

        // Sin scope, sí se ve
        $withoutScope = ClientVehicle::withoutTenantScope()->find($vehicle->id);
        $this->assertNotNull($withoutScope);
    }

    public function test_owner_contact_id_fk_set_null_on_contact_delete(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_contact_id' => $owner->id,
        ]);

        $owner->forceDelete();

        $fresh = $vehicle->fresh();
        $this->assertNull($fresh->owner_contact_id);
    }

    public function test_version_and_engine_number_are_nullable(): void
    {
        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'version' => null,
            'engine_number' => null,
        ]);

        $this->assertNull($vehicle->version);
        $this->assertNull($vehicle->engine_number);
    }

    public function test_can_create_vehicle_inline_from_work_order(): void
    {
        // Simula el callback createOptionUsing del WorkOrderResource
        $vehicle = ClientVehicle::create([
            'tenant_id' => $this->tenant->id,
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
        ]);

        $this->assertDatabaseHas('client_vehicles', [
            'id' => $vehicle->id,
            'plate' => 'INL-001',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2024,
            'version' => 'Touring',
            'vin' => '2HGFC2F93LH567890',
            'engine_number' => 'R18-98765',
            'current_mileage' => 5000,
            'color' => 'Gris',
        ]);
    }
}
