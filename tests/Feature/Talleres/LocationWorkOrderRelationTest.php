<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationWorkOrderRelationTest extends TestCase
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

    public function test_creates_work_order_with_location_id_from_same_tenant(): void
    {
        $location = Location::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
        ]);

        $this->assertEquals($location->id, $workOrder->location_id);
        $this->assertEquals($location->id, $workOrder->location->id);
    }

    public function test_allows_work_order_without_location_id(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => null,
        ]);

        $this->assertNull($workOrder->location_id);
        $this->assertNull($workOrder->location);
    }

    public function test_sets_location_id_to_null_when_location_is_force_deleted(): void
    {
        $location = Location::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
        ]);

        $location->forceDelete();

        $this->assertNull(WorkOrder::find($workOrder->id)->location_id);
    }

    public function test_location_has_many_work_orders(): void
    {
        $location = Location::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        WorkOrder::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
        ]);

        $this->assertEquals(2, $location->workOrders()->count());
    }

    public function test_location_work_orders_count(): void
    {
        $location = Location::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
        ]);

        $locationWithCount = Location::withCount('workOrders')->find($location->id);

        $this->assertEquals(3, $locationWithCount->work_orders_count);
    }
}
