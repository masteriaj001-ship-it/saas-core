<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Item;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Models\Warehouse;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StockConsumptionOnCompleteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Location $location;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->warehouse = Warehouse::factory()->default()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_stock_is_consumed_when_work_order_completed(): void
    {
        $item = Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 50,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'status' => WorkOrderStatusEnum::InProgress,
        ]);

        $workOrder->items()->create([
            'tenant_id' => $this->tenant->id,
            'item_id' => $item->id,
            'quantity' => 3,
            'description' => 'Filtro de aceite',
        ]);

        $workOrder->update(['status' => WorkOrderStatusEnum::Completed]);

        $this->assertEquals(47, $item->fresh()->stock);

        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'item_id' => $item->id,
            'movement_type' => 'exit',
            'quantity' => -3,
        ]);

        $this->assertDatabaseHas('work_order_items', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'item_id' => $item->id,
        ]);

        $woItem = $workOrder->items()->where('item_id', $item->id)->first();
        $this->assertNotNull($woItem->stock_movement_id);
        $this->assertNotNull($woItem->unit_cost_at_sale);
    }

    public function test_stock_not_consumed_when_status_not_completed(): void
    {
        $item = Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 50,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'status' => WorkOrderStatusEnum::Draft,
        ]);

        $workOrder->items()->create([
            'tenant_id' => $this->tenant->id,
            'item_id' => $item->id,
            'quantity' => 3,
            'description' => 'Filtro de aceite',
        ]);

        $workOrder->update(['status' => WorkOrderStatusEnum::InProgress]);

        $this->assertEquals(50, $item->fresh()->stock);

        $this->assertDatabaseMissing('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'item_id' => $item->id,
            'movement_type' => 'exit',
        ]);
    }

    public function test_no_stock_consumed_when_no_items(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'status' => WorkOrderStatusEnum::InProgress,
        ]);

        $workOrder->update(['status' => WorkOrderStatusEnum::Completed]);

        $this->assertDatabaseMissing('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'movement_type' => 'exit',
            'reference_type' => WorkOrder::class,
            'reference_id' => $workOrder->id,
        ]);
    }
}
