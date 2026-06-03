<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrders;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderItem;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Asset $asset;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();
        $this->asset = Asset::factory()->for($this->tenant)->create();
        $this->item = Item::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_can_create_work_order(): void
    {
        $workOrder = WorkOrder::create([
            'asset_id' => $this->asset->id,
            'title' => 'Mantenimiento preventivo',
            'code' => 'WO-0001',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'title' => 'Mantenimiento preventivo',
        ]);
    }

    public function test_can_add_items_to_work_order(): void
    {
        $workOrder = WorkOrder::create([
            'asset_id' => $this->asset->id,
            'title' => 'Reparación motor',
            'code' => 'WO-0002',
            'status' => 'in_progress',
        ]);

        $item = WorkOrderItem::create([
            'work_order_id' => $workOrder->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'unit_price' => 50000,
        ]);

        $this->assertDatabaseHas('work_order_items', [
            'id' => $item->id,
            'quantity' => 2,
        ]);

        $this->assertEquals(1, $workOrder->items()->count());
    }

    public function test_status_transitions_work(): void
    {
        $workOrder = WorkOrder::create([
            'asset_id' => $this->asset->id,
            'title' => 'Test estados',
            'code' => 'WO-0003',
            'status' => 'draft',
        ]);

        $workOrder->update(['status' => 'in_progress', 'started_at' => now()]);
        $this->assertEquals(WorkOrderStatusEnum::InProgress, $workOrder->fresh()->status);

        $workOrder->update(['status' => 'completed', 'completed_at' => now()]);
        $this->assertEquals(WorkOrderStatusEnum::Completed, $workOrder->fresh()->status);
    }
}
