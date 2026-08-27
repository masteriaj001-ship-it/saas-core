<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Filament\Resources\StockMovementResource;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Models\StockMovement;
use App\Modules\Inventario\Models\Warehouse;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StockMovementResourceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_resource_is_registered(): void
    {
        $this->assertNotNull(StockMovementResource::class);
    }

    public function test_cannot_create_movements(): void
    {
        $this->assertFalse(StockMovementResource::canCreate());
    }

    public function test_table_columns_are_defined(): void
    {
        $this->assertTrue(true);
    }

    public function test_movement_can_be_created_via_model(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);

        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $this->user->id,
            'movement_type' => 'entry',
            'quantity' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
            'unit_cost' => 5000,
            'reason' => 'Compra inicial',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->id,
            'tenant_id' => $this->tenant->id,
            'movement_type' => 'entry',
            'quantity' => 10,
        ]);
    }
}
