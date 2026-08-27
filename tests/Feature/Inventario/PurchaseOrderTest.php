<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Enums\PurchaseStatus;
use App\Modules\Inventario\Models\PurchaseOrder;
use App\Modules\Inventario\Models\PurchaseOrderItem;
use App\Modules\Inventario\Models\Supplier;
use App\Modules\Inventario\Models\Warehouse;
use App\Modules\Inventario\Services\PurchaseService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Warehouse $warehouse;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();
        $this->warehouse = Warehouse::factory()->default()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_purchase_order_can_be_created(): void
    {
        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_purchase_order_has_items(): void
    {
        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $item = Item::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
        ]);

        $this->assertCount(1, $po->items);
    }

    public function test_purchase_order_can_be_cancelled(): void
    {
        $po = PurchaseOrder::factory()->draft()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $po->update(['status' => PurchaseStatus::CANCELLED]);

        $this->assertEquals(PurchaseStatus::CANCELLED, $po->fresh()->status);
    }

    public function test_purchase_order_is_fully_received(): void
    {
        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $item = Item::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'quantity' => 10,
            'received_quantity' => 10,
        ]);

        $this->assertTrue($po->isFullyReceived());
    }

    public function test_receive_partial_purchase_order(): void
    {
        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => PurchaseStatus::ORDERED,
        ]);

        $item = Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 0,
        ]);

        PurchaseOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'quantity' => 10,
            'received_quantity' => 0,
            'unit_cost' => 50000,
        ]);

        $purchaseService = app(PurchaseService::class);
        $purchaseService->receive($po, [
            [
                'item_id' => $item->id,
                'quantity' => 5,
            ],
        ]);

        $po->refresh();
        $item->refresh();

        $this->assertEquals(PurchaseStatus::PARTIAL, $po->status);
        $this->assertEquals(5, $item->stock);
    }

    public function test_receive_full_purchase_order(): void
    {
        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => PurchaseStatus::ORDERED,
        ]);

        $item = Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 0,
        ]);

        PurchaseOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'quantity' => 10,
            'received_quantity' => 0,
            'unit_cost' => 50000,
        ]);

        $purchaseService = app(PurchaseService::class);
        $purchaseService->receive($po, [
            [
                'item_id' => $item->id,
                'quantity' => 10,
            ],
        ]);

        $po->refresh();
        $item->refresh();

        $this->assertEquals(PurchaseStatus::RECEIVED, $po->status);
        $this->assertEquals(10, $item->stock);
    }
}
