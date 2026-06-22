<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Actions\AdjustItemStockAction;
use App\Modules\Inventario\Enums\MovementTypeEnum;
use App\Modules\Inventario\Models\Warehouse;
use App\Modules\Inventario\Services\StockSyncService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StockSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Warehouse $warehouse;

    private Item $item;

    private AdjustItemStockAction $adjustStockAction;

    private StockSyncService $syncService;

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
        $this->syncService = app(StockSyncService::class);
    }

    public function test_sync_item_stock_corrects_drift(): void
    {
        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 10,
            reason: 'Initial',
        );

        $this->assertEquals(10, $this->item->fresh()->stock);

        $this->item->update(['stock' => 999]);

        $this->syncService->syncItemStock($this->item);

        $this->assertEquals(10, $this->item->fresh()->stock, 'syncItemStock should correct drift to 10.');
    }

    public function test_recalculate_item_stock_returns_sum(): void
    {
        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 7,
            reason: 'First entry',
        );

        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 3,
            reason: 'Second entry',
        );

        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Exit,
            quantity: 2,
            reason: 'Sale',
        );

        $recalculated = $this->syncService->recalculateItemStock($this->item->id);

        $this->assertEquals(8, $recalculated, '7 + 3 - 2 = 8');
    }

    public function test_sync_item_stock_does_not_update_when_no_drift(): void
    {
        $this->adjustStockAction->execute(
            item: $this->item,
            warehouse: $this->warehouse,
            movementType: MovementTypeEnum::Entry,
            quantity: 5,
            reason: 'Initial',
        );

        $originalUpdatedAt = $this->item->fresh()->updated_at;

        $this->syncService->syncItemStock($this->item);

        $this->assertEquals(
            $originalUpdatedAt->format('Y-m-d H:i:s'),
            $this->item->fresh()->updated_at->format('Y-m-d H:i:s'),
            'Should not update when no drift.',
        );
    }
}
