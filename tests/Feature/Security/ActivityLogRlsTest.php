<?php

declare(strict_types=1);

/**
 * RLS integration tests for the activity_log table.
 *
 * These tests use the `pgsql-rls` connection (app_user with NOBYPASSRLS)
 * to verify that PostgreSQL Row-Level Security protects activity_log.
 *
 * @see \Tests\Feature\ActivityLogAppScopeTest
 */

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ActivityLogRlsTest extends TestCase
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

    private function insertActivityViaSail(string $tenantId, string $description = 'Test activity'): void
    {
        DB::table('activity_log')->insert([
            'tenant_id' => $tenantId,
            'log_name' => 'default',
            'description' => $description,
            'event' => 'created',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cannot_read_other_tenant_activity(): void
    {
        $this->insertActivityViaSail($this->tenantA->id, 'Tenant A activity');

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $activities = DB::connection('pgsql-rls')
            ->table('activity_log')
            ->where('description', 'Tenant A activity')
            ->get();

        $this->assertCount(0, $activities, 'Tenant B should NOT see Tenant A\'s activity logs via RLS.');
    }

    public function test_cannot_update_other_tenant_activity(): void
    {
        $this->insertActivityViaSail($this->tenantA->id);

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $affected = DB::connection('pgsql-rls')
            ->table('activity_log')
            ->where('log_name', 'default')
            ->update(['description' => 'Hacked']);

        $this->assertEquals(0, $affected, 'Tenant B should NOT update Tenant A\'s activity logs via RLS.');
    }

    public function test_insert_without_tenant_context_fails(): void
    {
        $this->expectExceptionMessageMatches(
            '/tenant_context_missing|P0001|permission denied for table activity_log/'
        );

        DB::connection('pgsql-rls')
            ->table('activity_log')
            ->insert([
                'tenant_id' => $this->tenantA->id,
                'log_name' => 'default',
                'description' => 'No context',
                'event' => 'created',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
