<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WarehouseRlsTest extends TestCase
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

        $this->tenantA = Tenant::factory()->create(['name' => 'RLS Warehouse Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'RLS Warehouse Tenant B']);
    }

    protected function tearDown(): void
    {
        $this->tenantManager->clearTenantContext();
        parent::tearDown();
    }

    public function test_cannot_read_other_tenant_warehouse_via_rls(): void
    {
        DB::table('warehouses')->insert([
            'id' => DB::raw('gen_random_uuid()'),
            'tenant_id' => $this->tenantA->id,
            'code' => 'RLS-WH-A',
            'name' => 'RLS Test Warehouse',
            'is_default' => false,
            'is_active' => true,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $rows = DB::connection('pgsql-rls')
            ->table('warehouses')
            ->where('code', 'RLS-WH-A')
            ->get();

        $this->assertCount(0, $rows, 'Tenant B should NOT see Tenant A warehouse via RLS.');
    }

    public function test_cannot_update_other_tenant_warehouse_via_rls(): void
    {
        DB::table('warehouses')->insert([
            'id' => DB::raw('gen_random_uuid()'),
            'tenant_id' => $this->tenantA->id,
            'code' => 'RLS-WH-C',
            'name' => 'RLS Test Warehouse',
            'is_default' => false,
            'is_active' => true,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $affected = DB::connection('pgsql-rls')
            ->table('warehouses')
            ->where('code', 'RLS-WH-C')
            ->update(['name' => 'Hacked']);

        $this->assertEquals(0, $affected, 'Tenant B should NOT UPDATE Tenant A warehouse via RLS.');
    }

    public function test_insert_without_tenant_context_fails(): void
    {
        $this->expectExceptionMessageMatches(
            '/tenant_context_missing|P0001|permission denied for table warehouses/'
        );

        DB::connection('pgsql-rls')
            ->table('warehouses')
            ->insert([
                'id' => DB::connection('pgsql-rls')->raw('gen_random_uuid()'),
                'tenant_id' => $this->tenantA->id,
                'code' => 'RLS-WH-D',
                'name' => 'No Context',
                'is_default' => false,
                'is_active' => true,
                'metadata' => DB::connection('pgsql-rls')->raw("'{}'::jsonb"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
