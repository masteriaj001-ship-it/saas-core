<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class StockMovementRlsTest extends TestCase
{
    use RefreshDatabase;

    private TenantManager $tenantManager;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('database.connections.pgsql-rls')) {
            $this->markTestSkipped('pgsql-rls connection not configured.');
        }

        $this->tenantManager = app(TenantManager::class);

        $this->tenantA = Tenant::factory()->create(['name' => 'RLS SM Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'RLS SM Tenant B']);
    }

    protected function tearDown(): void
    {
        $this->tenantManager->clearTenantContext();
        parent::tearDown();
    }

    private function createPrerequisites(string $tenantId): array
    {
        $warehouseId = DB::table('warehouses')->insertGetId([
            'id' => DB::raw('gen_random_uuid()'),
            'tenant_id' => $tenantId,
            'code' => 'RLS-SM-WH-'.substr($tenantId, 0, 8),
            'name' => 'RLS Test WH',
            'is_default' => false,
            'is_active' => true,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = DB::table('items')->insertGetId([
            'id' => DB::raw('gen_random_uuid()'),
            'tenant_id' => $tenantId,
            'sku' => 'RLS-ITEM-'.substr($tenantId, 0, 8),
            'name' => 'RLS Test',
            'item_type' => 'product',
            'unit' => 'unit',
            'price' => 100,
            'cost' => 50,
            'stock' => 0,
            'min_stock' => 0,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$warehouseId, $itemId];
    }

    public function test_cannot_read_other_tenant_stock_movement_via_rls(): void
    {
        [$warehouseId, $itemId] = $this->createPrerequisites($this->tenantA->id);

        $movementId = DB::table('stock_movements')->insertGetId([
            'id' => DB::raw('gen_random_uuid()'),
            'tenant_id' => $this->tenantA->id,
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'movement_type' => 'entry',
            'quantity' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
            'reason' => 'Initial',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $rows = DB::connection('pgsql-rls')
            ->table('stock_movements')
            ->where('id', $movementId)
            ->get();

        $this->assertCount(0, $rows, 'Tenant B should NOT see Tenant A stock movements via RLS.');
    }

    public function test_insert_without_tenant_context_fails(): void
    {
        [$warehouseId, $itemId] = $this->createPrerequisites($this->tenantA->id);

        $this->expectExceptionMessageMatches(
            '/tenant_context_missing|P0001|permission denied for table stock_movements/'
        );

        DB::connection('pgsql-rls')
            ->table('stock_movements')
            ->insert([
                'id' => DB::connection('pgsql-rls')->raw('gen_random_uuid()'),
                'tenant_id' => $this->tenantA->id,
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'movement_type' => 'entry',
                'quantity' => 1,
                'stock_before' => 0,
                'stock_after' => 1,
                'reason' => 'No context test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
