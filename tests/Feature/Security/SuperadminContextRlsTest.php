<?php

declare(strict_types=1);

/**
 * RLS integration tests for GAP-002: Superadmin Tenant Context.
 *
 * These tests use the `pgsql-rls` connection (app_user with NOBYPASSRLS)
 * to verify that the PG function current_tenant_id_or_null() and the
 * superadmin context setting work correctly with PostgreSQL RLS.
 *
 * @see \Tests\Feature\Security\SuperadminContextAppScopeTest — Application-level tests.
 */

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SuperadminContextRlsTest extends TestCase
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

        $this->withoutVite();

        $this->tenantManager = app(TenantManager::class);

        $this->tenantA = Tenant::factory()->create(['name' => 'RLS Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'RLS Tenant B']);
    }

    private function setRlsContext(string $tenantId): void
    {
        DB::connection('pgsql-rls')->statement(
            "SELECT set_config('app.current_tenant_id', ?, false)",
            [$tenantId]
        );
    }

    private function clearRlsContext(): void
    {
        DB::connection('pgsql-rls')->statement(
            "SELECT set_config('app.current_tenant_id', '', false)"
        );
    }

    public function test_current_tenant_id_or_null_returns_null_without_context(): void
    {
        $this->clearRlsContext();

        $result = DB::connection('pgsql-rls')->selectOne(
            'SELECT public.current_tenant_id_or_null() AS tenant_id'
        );

        $this->assertNull($result->tenant_id, 'current_tenant_id_or_null() should return NULL when no context is set.');
    }

    public function test_current_tenant_id_or_null_returns_id_with_context(): void
    {
        $this->setRlsContext($this->tenantA->id);

        $result = DB::connection('pgsql-rls')->selectOne(
            'SELECT public.current_tenant_id_or_null() AS tenant_id'
        );

        $this->assertNotNull($result->tenant_id, 'current_tenant_id_or_null() should return a UUID when context is set.');
        $this->assertEquals(
            $this->tenantA->id,
            $result->tenant_id,
            'Should return the correct tenant UUID.'
        );

        $this->clearRlsContext();
    }

    public function test_current_tenant_id_still_throws_without_context(): void
    {
        $this->clearRlsContext();

        $this->expectExceptionMessageMatches(
            '/tenant_context_missing|P0001/'
        );

        DB::connection('pgsql-rls')->selectOne(
            'SELECT public.current_tenant_id()'
        );
    }
}
