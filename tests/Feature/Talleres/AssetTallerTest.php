<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Services\TenantManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTallerTest extends TestCase
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

    public function test_admin_can_create_client_vehicle_with_plate_brand_model_year(): void
    {
        $vehicle = ClientVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'ABC-123',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
        ]);

        $this->assertDatabaseHas('client_vehicles', [
            'id' => $vehicle->id,
            'plate' => 'ABC-123',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
        ]);
    }

    public function test_duplicate_plate_within_same_tenant_raises_exception(): void
    {
        ClientVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'ABC-123',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
        ]);

        $this->assertDatabaseHas('client_vehicles', ['plate' => 'ABC-123']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/unique|duplicate/i');

        ClientVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'ABC-123',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2021,
        ]);
    }

    public function test_same_plate_allowed_in_different_tenants(): void
    {
        $tenantB = Tenant::factory()->create();

        ClientVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'XYZ-789',
            'brand' => 'Nissan',
            'model' => 'Versa',
            'year' => 2022,
        ]);

        app(TenantManager::class)->setTenantContext($tenantB->id);

        $vehicleB = ClientVehicle::create([
            'tenant_id' => $tenantB->id,
            'plate' => 'XYZ-789',
            'brand' => 'Mazda',
            'model' => '3',
            'year' => 2023,
        ]);

        $this->assertDatabaseHas('client_vehicles', ['id' => $vehicleB->id, 'plate' => 'XYZ-789']);
    }

    public function test_can_create_client_vehicle_with_vin(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $vehicle = ClientVehicle::create([
            'tenant_id' => $this->tenant->id,
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'FORD-001',
            'brand' => 'Ford',
            'model' => 'Mustang',
            'year' => 2024,
            'owner_contact_id' => $owner->id,
        ]);

        $this->assertDatabaseHas('client_vehicles', [
            'id' => $vehicle->id,
            'vin' => '1FA6P8CF7L1234567',
            'owner_contact_id' => $owner->id,
        ]);
        $this->assertInstanceOf(Contact::class, $vehicle->owner);
        $this->assertTrue($vehicle->owner->is($owner));
    }

    public function test_vin_unique_per_tenant(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        ClientVehicle::create([
            'tenant_id' => $this->tenant->id,
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'FORD-001',
            'brand' => 'Ford',
            'model' => 'Mustang',
            'year' => 2024,
            'owner_contact_id' => $owner->id,
        ]);

        $this->assertDatabaseHas('client_vehicles', ['vin' => '1FA6P8CF7L1234567']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/unique|duplicate/i');

        ClientVehicle::create([
            'tenant_id' => $this->tenant->id,
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'HONDA-001',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2024,
            'owner_contact_id' => $owner->id,
        ]);
    }

    public function test_same_vin_allowed_different_tenants(): void
    {
        $ownerA = Contact::factory()->for($this->tenant)->client()->create();
        $tenantB = Tenant::factory()->create();
        $ownerB = Contact::factory()->for($tenantB)->client()->create();

        ClientVehicle::create([
            'tenant_id' => $this->tenant->id,
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'FORD-001',
            'brand' => 'Ford',
            'model' => 'Mustang',
            'year' => 2024,
            'owner_contact_id' => $ownerA->id,
        ]);

        app(TenantManager::class)->setTenantContext($tenantB->id);

        $vehicleB = ClientVehicle::create([
            'tenant_id' => $tenantB->id,
            'vin' => '1FA6P8CF7L1234567',
            'plate' => 'FIAT-001',
            'brand' => 'Fiat',
            'model' => '500',
            'year' => 2024,
            'owner_contact_id' => $ownerB->id,
        ]);

        $this->assertDatabaseHas('client_vehicles', ['id' => $vehicleB->id, 'vin' => '1FA6P8CF7L1234567']);
    }

    public function test_owner_relationship_returns_contact(): void
    {
        $owner = Contact::factory()->for($this->tenant)->client()->create();

        $vehicle = ClientVehicle::create([
            'tenant_id' => $this->tenant->id,
            'vin' => '3N1AB51D2XL123456',
            'plate' => 'NISS-001',
            'brand' => 'Nissan',
            'model' => 'Tsuru',
            'year' => 2010,
            'owner_contact_id' => $owner->id,
        ]);

        $this->assertInstanceOf(Contact::class, $vehicle->owner);
        $this->assertEquals($owner->id, $vehicle->owner->id);
        $this->assertEquals($owner->name, $vehicle->owner->name);
    }
}
