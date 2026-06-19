<?php

declare(strict_types=1);

namespace Tests\Feature\Budget;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BudgetRlsTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private TenantManager $tenantManager;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('database.connections.pgsql-rls')) {
            $this->markTestSkipped('pgsql-rls connection not configured.');
        }

        $this->tenantManager = app(TenantManager::class);

        $this->tenantA = Tenant::factory()->create(['name' => 'RLS Budget Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'RLS Budget Tenant B']);
    }

    protected function tearDown(): void
    {
        if (isset($this->tenantManager)) {
            $this->tenantManager->clearTenantContext();
        }

        // Clean up on pgsql (sail user has BYPASSRLS) to avoid RLS violations
        DB::connection('pgsql')->statement('DELETE FROM budget_items');
        DB::connection('pgsql')->statement('DELETE FROM budgets');
        DB::connection('pgsql')->statement('DELETE FROM tenants');

        parent::tearDown();
    }

    public function test_cannot_read_other_tenant_budget_via_rls(): void
    {
        $budgetId = DB::select('SELECT gen_random_uuid() AS id')[0]->id;

        DB::insert(
            "INSERT INTO budgets (id, tenant_id, code, contact_name, status, created_at, updated_at) VALUES (?, ?, ?, 'RLS Test', 'draft', NOW(), NOW())",
            [$budgetId, $this->tenantA->id, 'BGT-RLS-TEST-A']
        );

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $rows = DB::connection('pgsql-rls')
            ->table('budgets')
            ->where('id', $budgetId)
            ->get();

        $this->assertCount(0, $rows, 'Tenant B should NOT see Tenant A budget via RLS.');
    }

    public function test_can_read_same_tenant_budget_via_rls(): void
    {
        $this->tenantManager->setTenantContext($this->tenantA->id);

        $budgetId = DB::connection('pgsql-rls')->select('SELECT gen_random_uuid() AS id')[0]->id;

        DB::connection('pgsql-rls')->insert(
            "INSERT INTO budgets (id, tenant_id, code, contact_name, status, created_at, updated_at) VALUES (?, ?, ?, 'RLS Test', 'draft', NOW(), NOW())",
            [$budgetId, $this->tenantA->id, 'BGT-RLS-TEST-B']
        );

        $rows = DB::connection('pgsql-rls')
            ->table('budgets')
            ->where('id', $budgetId)
            ->get();

        $this->assertCount(1, $rows, 'Tenant A should see its own budget via RLS.');
    }

    public function test_cannot_read_other_tenant_budget_item_via_rls(): void
    {
        $this->tenantManager->setTenantContext($this->tenantA->id);

        $budgetId = DB::connection('pgsql-rls')->select('SELECT gen_random_uuid() AS id')[0]->id;
        $itemId = DB::connection('pgsql-rls')->select('SELECT gen_random_uuid() AS id')[0]->id;

        DB::connection('pgsql-rls')->insert(
            "INSERT INTO budgets (id, tenant_id, code, contact_name, status, created_at, updated_at) VALUES (?, ?, ?, 'RLS Test', 'draft', NOW(), NOW())",
            [$budgetId, $this->tenantA->id, 'BGT-RLS-TEST-C']
        );

        DB::connection('pgsql-rls')->insert(
            'INSERT INTO budget_items (id, tenant_id, budget_id, description, quantity, unit_price, subtotal, total, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 100, 100, 100, NOW(), NOW())',
            [$itemId, $this->tenantA->id, $budgetId, 'Item RLS']
        );

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $rows = DB::connection('pgsql-rls')
            ->table('budget_items')
            ->where('id', $itemId)
            ->get();

        $this->assertCount(0, $rows, 'Tenant B should NOT see Tenant A budget items via RLS.');
    }
}
