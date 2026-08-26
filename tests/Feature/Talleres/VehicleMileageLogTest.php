<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\VehicleMileageLog;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleMileageLogTest extends TestCase
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

    public function test_can_record_mileage_on_client_vehicle(): void
    {
        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_mileage' => 10000,
        ]);

        $log = $vehicle->recordMileage(15000, null, 'Cambio de aceite');

        $this->assertDatabaseHas('vehicle_mileage_logs', [
            'id' => $log->id,
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $vehicle->id,
            'mileage' => 15000,
            'notes' => 'Cambio de aceite',
        ]);
    }

    public function test_mileage_log_can_be_linked_to_work_order(): void
    {
        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $log = $vehicle->recordMileage(20000, $workOrder->id, 'Recepción');

        $this->assertEquals($workOrder->id, $log->work_order_id);
    }

    public function test_mileage_logs_are_ordered_by_recorded_at(): void
    {
        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $vehicle->mileageLogs()->create([
            'tenant_id' => $this->tenant->id,
            'mileage' => 15000,
            'recorded_at' => now()->subDays(30),
        ]);

        $vehicle->mileageLogs()->create([
            'tenant_id' => $this->tenant->id,
            'mileage' => 16000,
            'recorded_at' => now()->subDays(10),
        ]);

        $logs = $vehicle->mileageLogs()->orderBy('recorded_at')->get();

        $this->assertCount(2, $logs);
        $this->assertEquals(15000, $logs[0]->mileage);
        $this->assertEquals(16000, $logs[1]->mileage);
    }

    public function test_mileage_log_tenant_isolation(): void
    {
        $vehicle = ClientVehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $vehicle->mileageLogs()->create([
            'tenant_id' => $this->tenant->id,
            'mileage' => 15000,
            'recorded_at' => now(),
        ]);

        $tenantB = Tenant::factory()->create();
        app(TenantManager::class)->setTenantContext($tenantB->id);

        $visible = VehicleMileageLog::all();
        $this->assertCount(0, $visible);
    }
}
