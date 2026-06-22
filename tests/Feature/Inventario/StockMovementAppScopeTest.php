<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Actions\AdjustItemStockAction;
use App\Modules\Inventario\Enums\MovementTypeEnum;
use App\Modules\Inventario\Exceptions\InsufficientStockException;
use App\Modules\Inventario\Models\Warehouse;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StockMovementAppScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Warehouse $warehouse;

    private Item $item;

    private AdjustItemStockAction $adjustStockAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();
        $this->warehouse = Warehouse::factory()->default()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->item = Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 0,
        ]);

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->adjustStockAction = app(AdjustItemStockAction::class);
    }

    public function test_entry_increases_stock(): void
    {
        $movement = $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 10,
            reason: 'Purchase order',
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'movement_type' => 'entry',
            'quantity' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
        ]);
        $this->assertEquals(10, $this->item->fresh()->stock);
    }

    public function test_exit_decreases_stock(): void
    {
        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 20,
            reason: 'Stock initial',
        );

        $movement = $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Exit,
            quantity: 5,
            reason: 'Sale',
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'movement_type' => 'exit',
            'quantity' => -5,
            'stock_before' => 20,
            'stock_after' => 15,
        ]);
        $this->assertEquals(15, $this->item->fresh()->stock);
    }

    public function test_adjustment_out_decreases_stock(): void
    {
        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 10,
            reason: 'Initial',
        );

        $movement = $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::AdjustmentOut,
            quantity: 3,
            reason: 'Inventory correction - missing items',
            notes: 'Missing 3 units during physical count',
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'movement_type' => 'adjustment_out',
            'quantity' => -3,
            'stock_before' => 10,
            'stock_after' => 7,
            'notes' => 'Missing 3 units during physical count',
        ]);
        $this->assertEquals(7, $this->item->fresh()->stock);
    }

    public function test_adjustment_in_increases_stock(): void
    {
        $movement = $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::AdjustmentIn,
            quantity: 5,
            reason: 'Inventory correction - found items',
            notes: 'Found 5 units during physical count',
        );

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'movement_type' => 'adjustment_in',
            'quantity' => 5,
            'stock_before' => 0,
            'stock_after' => 5,
            'notes' => 'Found 5 units during physical count',
        ]);
        $this->assertEquals(5, $this->item->fresh()->stock);
    }

    public function test_stock_movement_with_reference(): void
    {
        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 10,
            reason: 'Initial stock',
        );

        $movement = $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Exit,
            quantity: 2,
            reason: 'Sale INV-000001',
            reference: $this->item,
            unitCost: 15000.50,
        );

        $this->assertEquals($this->item->getMorphClass(), $movement->reference_type);
        $this->assertEquals($this->item->id, $movement->reference_id);
        $this->assertEquals('15000.50', (string) $movement->unit_cost);
    }

    public function test_quantity_must_be_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 0,
        );
    }

    public function test_multiple_movements_calculate_correct_accumulated_stock(): void
    {
        $this->adjustStockAction->execute(item: $this->item, warehouse: $this->warehouse, movementType: MovementTypeEnum::Entry, quantity: 10, reason: 'Initial');
        $this->adjustStockAction->execute(item: $this->item, warehouse: $this->warehouse, movementType: MovementTypeEnum::Entry, quantity: 5, reason: 'Restock');
        $this->adjustStockAction->execute(item: $this->item, warehouse: $this->warehouse, movementType: MovementTypeEnum::Exit, quantity: 3, reason: 'Sale');
        $this->adjustStockAction->execute(item: $this->item, warehouse: $this->warehouse, movementType: MovementTypeEnum::Entry, quantity: 2, reason: 'Return');

        $this->assertEquals(14, $this->item->fresh()->stock, '10 + 5 - 3 + 2 = 14');
    }

    public function test_exit_negative_stock_is_blocked_by_default(): void
    {
        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 5,
            reason: 'Initial',
        );

        $this->expectException(InsufficientStockException::class);

        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Exit,
            quantity: 10,
            reason: 'Over-sale',
        );
    }

    public function test_adjustment_out_negative_stock_is_blocked(): void
    {
        $this->expectException(InsufficientStockException::class);

        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::AdjustmentOut,
            quantity: 1,
            reason: 'Adjustment on empty stock',
        );
    }

    public function test_transfer_out_negative_stock_is_blocked(): void
    {
        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 3,
            reason: 'Initial',
        );

        $this->expectException(InsufficientStockException::class);

        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::TransferOut,
            quantity: 10,
            reason: 'Transfer too many',
        );
    }

    public function test_entry_never_blocked_by_negative_stock(): void
    {
        $movement = $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 10,
            reason: 'Entry always allowed',
        );

        $this->assertEquals(10, $movement->stock_after);
    }
}
