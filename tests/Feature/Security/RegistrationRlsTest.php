<?php

declare(strict_types=1);

/**
 * Full registration flow under REAL PostgreSQL RLS.
 *
 * The default test connection (`sail`) has BYPASSRLS=true, so the normal
 * RegistrationTest never exercises RLS. This test switches the application
 * default connection to `pgsql-rls` (app_user with NOBYPASSRLS) so every
 * query in RegisterService runs under enforced row-level security — the
 * same conditions as production.
 *
 * Regression coverage: registration used to fail on production because
 * Tenant::create() logged to activity_log BEFORE setTenantContext, so
 * current_tenant_id() raised "tenant_context_missing".
 *
 * @see app/Services/Auth/RegisterService.php
 */

namespace Tests\Feature\Security;

use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RegistrationRlsTest extends TestCase
{
    use RefreshDatabase;

    private const CONNECTION = 'pgsql-rls';

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('database.connections.'.self::CONNECTION)) {
            $this->markTestSkipped('pgsql-rls connection not configured.');
        }

        $this->withoutVite();

        config(['database.default' => self::CONNECTION]);
        DB::connection(self::CONNECTION)->beginTransaction();
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->clearTenantContext();

        $connection = DB::connection(self::CONNECTION);
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        config(['database.default' => 'pgsql']);
        DB::purge(self::CONNECTION);

        parent::tearDown();
    }

    public function test_registration_succeeds_under_real_rls(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'RLS Registrant',
            'business_name' => 'RLS Registration Biz',
            'email' => 'rls-registration@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $response->assertRedirect('/admin');

        $this->assertDatabaseHas('users', [
            'email' => 'rls-registration@example.com',
        ]);

        $user = DB::connection(self::CONNECTION)
            ->table('users')
            ->where('email', 'rls-registration@example.com')
            ->first();

        $this->assertNotNull($user, 'User must be visible under RLS connection.');
        $this->assertNotNull($user->tenant_id, 'User must belong to a tenant.');
    }

    public function test_registration_logs_activity_under_real_rls(): void
    {
        $this->post(route('register'), [
            'name' => 'RLS Audited',
            'business_name' => 'RLS Audited Biz',
            'email' => 'rls-audited@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $user = DB::connection(self::CONNECTION)
            ->table('users')
            ->where('email', 'rls-audited@example.com')
            ->first();

        $this->assertNotNull($user);

        app(TenantManager::class)->setTenantContext($user->tenant_id);

        $activity = DB::connection(self::CONNECTION)
            ->table('activity_log')
            ->where('subject_type', 'App\\Models\\Tenant')
            ->where('event', 'created')
            ->get();

        $this->assertCount(1, $activity, 'Tenant creation must be auditable under RLS.');
    }
}
