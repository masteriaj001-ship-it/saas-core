<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Actions\AdjustItemStockAction;
use App\Modules\Inventario\Actions\TransferStockAction;
use App\Modules\Inventario\Enums\MovementTypeEnum;
use App\Modules\Inventario\Exceptions\InsufficientStockException;
use App\Modules\Inventario\Models\StockMovement;
use App\Modules\Inventario\Models\Warehouse;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TransferStockActionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Warehouse $origin;

    private Warehouse $destination;

    private Item $item;

    private TransferStockAction $transferAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();

        $this->origin = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'ORIGIN',
            'name' => 'Origin Warehouse',
        ]);
        $this->destination = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'DEST',
            'name' => 'Destination Warehouse',
        ]);
        $this->item = Item::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 0,
        ]);

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->transferAction = app(TransferStockAction::class);
    }

    private function addStock(int $quantity, ?Warehouse $warehouse = null): void
    {
        $wh = $warehouse ?? $this->origin;
        app(AdjustItemStockAction::class)->execute(
            item: $this->item,
            warehouse: $wh,
            movementType: MovementTypeEnum::Entry,
            quantity: $quantity,
            reason: 'Test initial stock',
        );
    }

    public function test_transfer_creates_out_and_in_movements(): void
    {
        $this->addStock(20);

        $result = $this->transferAction->execute(
            item: $this->item,
            origin: $this->origin,
            destination: $this->destination,
            quantity: 5,
            reason: 'Reabastecimiento',
            user: $this->user,
        );

        $this->assertArrayHasKey('out', $result);
        $this->assertArrayHasKey('in', $result);

        $this->assertEquals('transfer_out', $result['out']->movement_type);
        $this->assertEquals(-5, $result['out']->quantity);
        $this->assertEquals($this->origin->id, $result['out']->warehouse_id);
        $this->assertEquals(20, $result['out']->stock_before);
        $this->assertEquals(15, $result['out']->stock_after);

        $this->assertEquals('transfer_in', $result['in']->movement_type);
        $this->assertEquals(5, $result['in']->quantity);
        $this->assertEquals($this->destination->id, $result['in']->warehouse_id);
        $this->assertEquals(0, $result['in']->stock_before);
        $this->assertEquals(5, $result['in']->stock_after);

        $this->assertEquals(
            $result['out']->transfer_group_id,
            $result['in']->transfer_group_id,
            'Both movements should share the same transfer_group_id.',
        );
    }

    public function test_transfer_preserves_total_stock(): void
    {
        $this->addStock(20);

        $this->transferAction->execute(
            item: $this->item,
            origin: $this->origin,
            destination: $this->destination,
            quantity: 5,
            reason: 'Transfer',
        );

        $this->assertEquals(20, $this->item->fresh()->stock, 'Total stock should remain unchanged.');
    }

    public function test_transfer_same_warehouse_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Origin and destination warehouses must be different.');

        $this->transferAction->execute(
            item: $this->item,
            origin: $this->origin,
            destination: $this->origin,
            quantity: 5,
            reason: 'Same warehouse',
        );
    }

    public function test_transfer_insufficient_stock_blocked(): void
    {
        $this->addStock(3);

        $this->expectException(InsufficientStockException::class);

        $this->transferAction->execute(
            item: $this->item,
            origin: $this->origin,
            destination: $this->destination,
            quantity: 10,
            reason: 'Over-transfer',
        );
    }

    public function test_transfer_no_movements_created_on_failure(): void
    {
        $this->addStock(3);

        try {
            $this->transferAction->execute(
                item: $this->item,
                origin: $this->origin,
                destination: $this->destination,
                quantity: 10,
                reason: 'Should fail',
            );
        } catch (InsufficientStockException) {
        }

        $count = StockMovement::where('item_id', $this->item->id)->count();

        $this->assertEquals(1, $count, 'Only the initial entry should exist, no transfer movements.');
    }

    public function test_transfer_quantity_must_be_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be positive.');

        $this->transferAction->execute(
            item: $this->item,
            origin: $this->origin,
            destination: $this->destination,
            quantity: 0,
            reason: 'Zero',
        );
    }
}
