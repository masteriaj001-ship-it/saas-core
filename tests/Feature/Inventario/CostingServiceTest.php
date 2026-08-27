<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Models\PurchaseOrder;
use App\Modules\Inventario\Models\Supplier;
use App\Modules\Inventario\Models\Warehouse;
use App\Modules\Inventario\Services\CostingService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CostingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();
        $this->item = Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 10,
            'average_cost' => 10000,
        ]);

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_average_cost_is_recalled_on_entry(): void
    {
        $costingService = app(CostingService::class);

        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'OC-TEST-001',
            'status' => 'draft',
        ]);

        $costingService->recalculateAverageCost(
            item: $this->item,
            quantityReceived: 10,
            unitCost: 15000,
            source: $po,
        );

        $this->item->refresh();

        // stockBefore = 10 - 10 = 0
        // totalCostBefore = 0 * 10000 = 0
        // totalCostNew = 0 + (10 * 15000) = 150000
        // newStock = 0 + 10 = 10
        // average = 150000 / 10 = 15000
        $this->assertEquals(15000, $this->item->average_cost);
    }

    public function test_cost_history_is_created(): void
    {
        $costingService = app(CostingService::class);

        $item = Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 10,
            'average_cost' => 10000,
        ]);

        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'OC-TEST-002',
            'status' => 'draft',
        ]);

        $costingService->recalculateAverageCost(
            item: $item,
            quantityReceived: 10,
            unitCost: 15000,
            source: $po,
        );

        $this->assertDatabaseHas('item_cost_histories', [
            'item_id' => $item->id,
            'previous_cost' => 10000,
            'new_cost' => 15000,
            'quantity_affected' => 10,
        ]);
    }
}
