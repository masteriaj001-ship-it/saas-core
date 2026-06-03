<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SpatieTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_roles_are_isolated_between_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(TenantManager::class)->setTenantContext($tenantA->id);
        $this->seed(RolePermissionSeeder::class);
        $userA = User::factory()->for($tenantA)->create();
        $userA->assignRole('owner');

        app(TenantManager::class)->setTenantContext($tenantB->id);
        $this->seed(RolePermissionSeeder::class);
        $userB = User::factory()->for($tenantB)->create();
        $userB->assignRole('viewer');

        app(TenantManager::class)->setTenantContext($tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($userA);

        $rolesA = $userA->roles;
        $this->assertCount(1, $rolesA);
        $this->assertEquals('owner', $rolesA->first()->name);
        $this->assertFalse($rolesA->pluck('name')->contains('viewer'));
    }

    public function test_permissions_are_isolated_between_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(TenantManager::class)->setTenantContext($tenantA->id);
        $this->seed(RolePermissionSeeder::class);
        $userA = User::factory()->for($tenantA)->create();
        $userA->assignRole('owner');

        app(TenantManager::class)->setTenantContext($tenantB->id);
        $this->seed(RolePermissionSeeder::class);
        $userB = User::factory()->for($tenantB)->create();
        $userB->assignRole('viewer');

        app(TenantManager::class)->setTenantContext($tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permsA = $userA->getAllPermissions();
        $this->assertCount(20, $permsA);
        $this->assertContains('create_work_orders', $permsA->pluck('name'));
        $this->assertContains('delete_work_orders', $permsA->pluck('name'));

        app(TenantManager::class)->setTenantContext($tenantB->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permsB = $userB->getAllPermissions();
        $this->assertCount(5, $permsB);
        $this->assertContains('view_work_orders', $permsB->pluck('name'));
        $this->assertNotContains('create_work_orders', $permsB->pluck('name'));
    }

    public function test_belongsto_tenant_global_scope_filters_roles_by_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(TenantManager::class)->setTenantContext($tenantA->id);
        $this->seed(RolePermissionSeeder::class);

        app(TenantManager::class)->setTenantContext($tenantB->id);
        $this->seed(RolePermissionSeeder::class);

        app(TenantManager::class)->setTenantContext($tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $scopedRoles = Role::get();
        $this->assertCount(4, $scopedRoles);

        $unscopedRoles = Role::withoutTenantScope()->get();
        $this->assertCount(8, $unscopedRoles);
    }

    public function test_rls_policies_exist_on_all_spatie_tables(): void
    {
        $tables = ['roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'];

        foreach ($tables as $table) {
            $policies = DB::select(
                'SELECT policyname, cmd FROM pg_policies WHERE tablename = ? ORDER BY cmd',
                [$table]
            );
            $this->assertCount(4, $policies, "Table {$table} should have 4 RLS policies");

            $cmds = collect($policies)->pluck('cmd')->sort()->values()->all();
            $this->assertEquals(['DELETE', 'INSERT', 'SELECT', 'UPDATE'], $cmds);

            $forced = DB::select(
                'SELECT relforcerowsecurity FROM pg_class WHERE relname = ?',
                [$table]
            );
            $this->assertTrue(
                in_array($forced[0]->relforcerowsecurity, [true, 't'], true),
                "Table {$table} must have FORCE RLS enabled"
            );
        }
    }
}
