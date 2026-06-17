<?php

declare(strict_types=1);

/**
 * RLS integration tests for the module activation system.
 *
 * These tests use the `pgsql-rls` connection (app_user with NOBYPASSRLS)
 * to verify that PostgreSQL Row-Level Security actually protects tenant_modules.
 *
 * Queries are inserted via the default connection (sail, BYPASSRLS=true) to
 * bypass RLS, then read/updated via pgsql-rls to verify RLS enforcement.
 *
 * @see \Tests\Feature\TenantModuleAppScopeTest — Application-level tests.
 */

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TenantModuleRlsTest extends TestCase
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

    private function insertViaSail(array $data): void
    {
        DB::table('tenant_modules')->insert(array_merge([
            'id' => DB::raw('gen_random_uuid()'),
            'module_slug' => 'taller',
            'is_active' => true,
            'activated_at' => now(),
        ], $data));
    }

    public function test_cannot_read_other_tenant_module(): void
    {
        $this->insertViaSail([
            'tenant_id' => $this->tenantA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $modules = DB::connection('pgsql-rls')
            ->table('tenant_modules')
            ->where('module_slug', 'taller')
            ->get();

        $this->assertCount(0, $modules, 'Tenant B should NOT see Tenant A\'s module via RLS.');
    }

    public function test_cannot_update_other_tenant_module(): void
    {
        $this->insertViaSail([
            'tenant_id' => $this->tenantA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $affected = DB::connection('pgsql-rls')
            ->table('tenant_modules')
            ->where('module_slug', 'taller')
            ->update(['is_active' => false]);

        $this->assertEquals(0, $affected, 'Tenant B should NOT be able to UPDATE Tenant A\'s module via RLS.');
    }

    public function test_insert_without_tenant_context_fails(): void
    {
        $this->expectExceptionMessageMatches(
            '/tenant_context_missing|P0001|permission denied for table tenant_modules/'
        );

        DB::connection('pgsql-rls')
            ->table('tenant_modules')
            ->insert([
                'id' => DB::connection('pgsql-rls')->raw('gen_random_uuid()'),
                'tenant_id' => $this->tenantA->id,
                'module_slug' => 'taller',
                'is_active' => true,
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
