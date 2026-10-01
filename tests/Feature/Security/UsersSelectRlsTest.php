<?php

declare(strict_types=1);

/**
 * RLS tests for the users_select policy.
 *
 * Regression coverage for the evaluation-order bug: the original policy used
 * `current_setting(...) IS NULL OR ... OR tenant_id = current_tenant_id()`.
 * PostgreSQL does not guarantee left-to-right OR evaluation, so the planner
 * could raise "tenant_context_missing" on SELECT without a tenant context
 * (breaking login, password reset and the registration unique-email check).
 *
 * Uses the `pgsql-rls` connection (app_user with NOBYPASSRLS) so PostgreSQL
 * actually enforces RLS — the default `sail` connection has BYPASSRLS=true.
 *
 * RefreshDatabase wraps the DEFAULT connection in a transaction, which is
 * invisible to the separate pgsql-rls session. Data is therefore created and
 * read inside a dedicated transaction on pgsql-rls itself, rolled back in
 * tearDown.
 *
 * @see database/migrations/2026_09_30_235149_fix_users_select_policy_evaluation_order.php
 */

namespace Tests\Feature\Security;

use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UsersSelectRlsTest extends TestCase
{
    use RefreshDatabase;

    private const CONNECTION = 'pgsql-rls';

    private TenantManager $tenantManager;

    private string $tenantAId;

    private string $tenantBId;

    private string $userAEmail = 'user-a@example.com';

    private string $userBEmail = 'user-b@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('database.connections.pgsql-rls')) {
            $this->markTestSkipped('pgsql-rls connection not configured.');
        }

        $this->tenantManager = app(TenantManager::class);

        DB::connection(self::CONNECTION)->beginTransaction();

        $this->tenantAId = (string) Str::uuid();
        $this->tenantBId = (string) Str::uuid();

        $now = now();
        DB::connection(self::CONNECTION)->table('tenants')->insert([
            ['id' => $this->tenantAId, 'name' => 'Users RLS Tenant A', 'slug' => 'users-rls-tenant-a', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => $this->tenantBId, 'name' => 'Users RLS Tenant B', 'slug' => 'users-rls-tenant-b', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->insertUserViaRls($this->tenantAId, $this->userAEmail);
        $this->insertUserViaRls($this->tenantBId, $this->userBEmail);

        $this->tenantManager->clearTenantContext();
    }

    protected function tearDown(): void
    {
        $this->tenantManager->clearTenantContext();

        $connection = DB::connection(self::CONNECTION);
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    private function insertUserViaRls(string $tenantId, string $email): void
    {
        $this->tenantManager->setTenantContext($tenantId);

        DB::connection(self::CONNECTION)->table('users')->insert([
            'id' => DB::connection(self::CONNECTION)->raw('gen_random_uuid()'),
            'tenant_id' => $tenantId,
            'name' => 'RLS User',
            'email' => $email,
            'password' => 'irrelevant-hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantManager->clearTenantContext();
    }

    public function test_select_without_tenant_context_does_not_raise(): void
    {
        $rows = DB::connection(self::CONNECTION)
            ->table('users')
            ->where('email', $this->userAEmail)
            ->count();

        $this->assertEquals(1, $rows, 'SELECT without tenant context must work (login/reset/unique-email flows).');
    }

    public function test_repeated_select_without_context_survives_generic_plans(): void
    {
        // PostgreSQL switches prepared statements to generic plans after a few
        // executions. Generic plans may reorder OR subexpressions, evaluating
        // `tenant_id = current_tenant_id()` before the NULL/empty guards and
        // raising "tenant_context_missing" — the production registration bug.
        // force_generic_plan reproduces that ordering deterministically.
        $connection = DB::connection(self::CONNECTION);

        try {
            $connection->statement('SET plan_cache_mode = force_generic_plan');
            $connection->select(
                'SELECT count(*) FROM users WHERE email = ? AND deleted_at IS NULL',
                [$this->userAEmail]
            );
        } catch (\Throwable $e) {
            $this->fail("SELECT without tenant context raised on generic plan: {$e->getMessage()}");
        } finally {
            $connection->statement('SET plan_cache_mode = auto');
        }

        $this->assertTrue(true);
    }

    public function test_select_without_context_sees_all_users(): void
    {
        $rows = DB::connection(self::CONNECTION)
            ->table('users')
            ->whereIn('email', [$this->userAEmail, $this->userBEmail])
            ->count();

        $this->assertEquals(2, $rows, 'No-context SELECT must remain unrestricted (pre-existing contract).');
    }

    public function test_tenant_context_isolates_users(): void
    {
        $this->tenantManager->setTenantContext($this->tenantAId);

        $rows = DB::connection(self::CONNECTION)
            ->table('users')
            ->whereIn('email', [$this->userAEmail, $this->userBEmail])
            ->get();

        $this->assertCount(1, $rows, 'Tenant A must only see its own users.');
        $this->assertEquals($this->userAEmail, $rows[0]->email);
    }

    public function test_tenant_context_cannot_see_other_tenant_user(): void
    {
        $this->tenantManager->setTenantContext($this->tenantBId);

        $rows = DB::connection(self::CONNECTION)
            ->table('users')
            ->where('email', $this->userAEmail)
            ->count();

        $this->assertEquals(0, $rows, 'Tenant B must NOT see Tenant A\'s user via RLS.');
    }
}
