<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Contact;
use App\Models\Tenant;
use App\Modules\Talleres\Models\ClientVehicle;
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
}
