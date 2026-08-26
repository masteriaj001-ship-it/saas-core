<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientVehicleWorkOrderTest extends TestCase
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

    public function test_work_order_can_be_linked_to_client_vehicle(): void
    {
        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $vehicle->id,
        ]);

        $this->assertEquals($vehicle->id, $workOrder->clientVehicle->id);
    }

    public function test_client_vehicle_has_many_work_orders(): void
    {
        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $vehicle->id,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $vehicle->id,
        ]);

        $this->assertCount(2, $vehicle->workOrders);
    }

    public function test_work_order_tenant_isolation_for_client_vehicle(): void
    {
        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $vehicle->id,
        ]);

        $tenantB = Tenant::factory()->create();
        $vehicleB = ClientVehicle::factory()->create([
            'tenant_id' => $tenantB->id,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $tenantB->id,
            'client_vehicle_id' => $vehicleB->id,
        ]);

        app(TenantManager::class)->setTenantContext($tenantB->id);

        $visibleVehicles = ClientVehicle::all();
        $this->assertCount(1, $visibleVehicles);
        $this->assertEquals($vehicleB->id, $visibleVehicles->first()->id);
    }

    public function test_work_order_without_client_vehicle_is_valid(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => null,
        ]);

        $this->assertNull($workOrder->client_vehicle_id);
        $this->assertNull($workOrder->clientVehicle);
    }

    public function test_deleting_client_vehicle_sets_null_on_work_order(): void
    {
        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $vehicle->id,
        ]);

        $vehicle->forceDelete();

        $workOrder->refresh();
        $this->assertNull($workOrder->client_vehicle_id);
    }
}
