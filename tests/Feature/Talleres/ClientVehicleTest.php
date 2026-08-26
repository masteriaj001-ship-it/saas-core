<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Contact;
use App\Models\Tenant;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientVehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_client_vehicle(): void
    {
        $tenant = Tenant::factory()->create();
        $contact = Contact::factory()->create(['tenant_id' => $tenant->id]);

        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $tenant->id,
            'owner_contact_id' => $contact->id,
            'plate' => 'ABC-123',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
        ]);

        $this->assertDatabaseHas('client_vehicles', [
            'id' => $vehicle->id,
            'tenant_id' => $tenant->id,
            'plate' => 'ABC-123',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
        ]);
    }

    public function test_client_vehicle_belongs_to_owner(): void
    {
        $tenant = Tenant::factory()->create();
        $contact = Contact::factory()->create(['tenant_id' => $tenant->id]);

        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $tenant->id,
            'owner_contact_id' => $contact->id,
        ]);

        $this->assertEquals($contact->id, $vehicle->owner->id);
    }

    public function test_client_vehicle_has_work_orders(): void
    {
        $tenant = Tenant::factory()->create();

        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->assertIsIterable($vehicle->workOrders);
    }

    public function test_client_vehicle_has_mileage_logs(): void
    {
        $tenant = Tenant::factory()->create();

        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->assertIsIterable($vehicle->mileageLogs);
    }

    public function test_can_search_by_plate(): void
    {
        $tenant = Tenant::factory()->create();

        ClientVehicle::factory()->create([
            'tenant_id' => $tenant->id,
            'plate' => 'XYZ-789',
        ]);

        $found = ClientVehicle::byPlate('XYZ-789')->first();
        $this->assertNotNull($found);
        $this->assertEquals('XYZ-789', $found->plate);
    }

    public function test_can_search_by_owner(): void
    {
        $tenant = Tenant::factory()->create();
        $contact = Contact::factory()->create(['tenant_id' => $tenant->id]);

        ClientVehicle::factory()->create([
            'tenant_id' => $tenant->id,
            'owner_contact_id' => $contact->id,
        ]);

        $found = ClientVehicle::byOwner($contact->id)->first();
        $this->assertNotNull($found);
        $this->assertEquals($contact->id, $found->owner_contact_id);
    }

    public function test_client_vehicle_tenant_isolation(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        ClientVehicle::factory()->create(['tenant_id' => $tenantA->id, 'plate' => 'AAA-000']);
        ClientVehicle::factory()->create(['tenant_id' => $tenantB->id, 'plate' => 'BBB-111']);

        app(TenantManager::class)->setTenantContext($tenantA->id);
        $visibleA = ClientVehicle::byPlate('AAA-000')->get();
        $this->assertCount(1, $visibleA);
        $this->assertEquals($tenantA->id, $visibleA->first()->tenant_id);

        app(TenantManager::class)->setTenantContext($tenantB->id);
        $visibleB = ClientVehicle::byPlate('BBB-111')->get();
        $this->assertCount(1, $visibleB);
        $this->assertEquals($tenantB->id, $visibleB->first()->tenant_id);
    }
}
