<?php

declare(strict_types=1);

/**
 * RLS integration tests for work order checklist items.
 *
 * These tests use the `pgsql-rls` connection (app_user with NOBYPASSRLS)
 * to verify that PostgreSQL Row-Level Security protects work_order_checklist_items.
 *
 * Queries are inserted via the default connection (sail, BYPASSRLS=true) to
 * bypass RLS, then read/updated via pgsql-rls to verify RLS enforcement.
 *
 * @see \Tests\Feature\Talleres\WorkOrderChecklistAppScopeTest
 */

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WorkOrderChecklistRlsTest extends TestCase
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

        $this->tenantA = Tenant::factory()->create(['name' => 'RLS Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'RLS Tenant B']);
    }

    protected function tearDown(): void
    {
        $this->tenantManager->clearTenantContext();
        parent::tearDown();
    }

    private function insertChecklistViaSail(string $tenantId, string $workOrderId, string $task = 'Check item'): void
    {
        DB::table('work_order_checklist_items')->insert([
            'id' => DB::raw('gen_random_uuid()'),
            'tenant_id' => $tenantId,
            'work_order_id' => $workOrderId,
            'task' => $task,
            'status' => 'pending',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cannot_read_other_tenant_checklist(): void
    {
        $workOrderA = WorkOrder::factory()->for($this->tenantA)->create();

        $this->insertChecklistViaSail($this->tenantA->id, $workOrderA->id, 'Tenant A task');

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $items = DB::connection('pgsql-rls')
            ->table('work_order_checklist_items')
            ->where('task', 'Tenant A task')
            ->get();

        $this->assertCount(0, $items, 'Tenant B should NOT see Tenant A\'s checklist items via RLS.');
    }

    public function test_cannot_update_other_tenant_checklist(): void
    {
        $workOrderA = WorkOrder::factory()->for($this->tenantA)->create();

        $this->insertChecklistViaSail($this->tenantA->id, $workOrderA->id);

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $affected = DB::connection('pgsql-rls')
            ->table('work_order_checklist_items')
            ->where('position', 0)
            ->update(['status' => 'done']);

        $this->assertEquals(0, $affected, 'Tenant B should NOT update Tenant A\'s checklist items via RLS.');
    }

    public function test_insert_without_tenant_context_fails(): void
    {
        $workOrderA = WorkOrder::factory()->for($this->tenantA)->create();

        $this->expectExceptionMessageMatches(
            '/tenant_context_missing|P0001|permission denied for table work_order_checklist_items/'
        );

        DB::connection('pgsql-rls')
            ->table('work_order_checklist_items')
            ->insert([
                'id' => DB::connection('pgsql-rls')->raw('gen_random_uuid()'),
                'tenant_id' => $this->tenantA->id,
                'work_order_id' => $workOrderA->id,
                'task' => 'No context task',
                'status' => 'pending',
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function test_force_delete_cascades_to_checklist_items(): void
    {
        $workOrderA = WorkOrder::factory()->for($this->tenantA)->create();

        $this->insertChecklistViaSail($this->tenantA->id, $workOrderA->id, 'Cascade test');

        $this->assertEquals(1, DB::table('work_order_checklist_items')
            ->where('task', 'Cascade test')
            ->count(), 'Item should exist before cascade.');

        $workOrderA->forceDelete();

        $this->assertEquals(0, DB::table('work_order_checklist_items')
            ->where('task', 'Cascade test')
            ->count(), 'Force deleting a work order should cascade delete its checklist items.');
    }

    public function test_soft_delete_does_not_cascade(): void
    {
        $workOrderA = WorkOrder::factory()->for($this->tenantA)->create();

        $this->insertChecklistViaSail($this->tenantA->id, $workOrderA->id, 'Soft cascade test');

        $workOrderA->delete();

        $this->assertEquals(1, DB::table('work_order_checklist_items')
            ->where('task', 'Soft cascade test')
            ->count(), 'Soft deleting a work order should NOT cascade delete its checklist items.');
    }
}
