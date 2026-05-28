<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Permission;
use App\Models\Tenant;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SpatieCacheIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_permission_cache_is_isolated_per_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(TenantManager::class)->setTenantContext($tenantA->id);
        $this->seed(RolePermissionSeeder::class);

        app(TenantManager::class)->setTenantContext($tenantB->id);
        $this->seed(RolePermissionSeeder::class);
        Permission::create(['name' => 'tenant_b_exclusive']);

        app(TenantManager::class)->setTenantContext($tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permsA = app(PermissionRegistrar::class)->getPermissions();
        $namesA = $permsA->pluck('name');
        $this->assertContains('create_work_orders', $namesA);
        $this->assertNotContains('tenant_b_exclusive', $namesA);

        app(TenantManager::class)->setTenantContext($tenantB->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permsB = app(PermissionRegistrar::class)->getPermissions();
        $namesB = $permsB->pluck('name');
        $this->assertContains('tenant_b_exclusive', $namesB);
        $this->assertCount(21, $permsB);
    }

    public function test_forget_cached_permissions_clears_in_memory_cache(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(TenantManager::class)->setTenantContext($tenantA->id);
        $this->seed(RolePermissionSeeder::class);

        app(TenantManager::class)->setTenantContext($tenantB->id);
        $this->seed(RolePermissionSeeder::class);
        Permission::create(['name' => 'tenant_b_exclusive']);

        app(TenantManager::class)->setTenantContext($tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $loadedA = app(PermissionRegistrar::class)->getPermissions();
        $this->assertCount(20, $loadedA);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(TenantManager::class)->setTenantContext($tenantB->id);
        $loadedB = app(PermissionRegistrar::class)->getPermissions();
        $this->assertCount(21, $loadedB);
        $this->assertContains('tenant_b_exclusive', $loadedB->pluck('name'));
    }

    public function test_permissions_reload_with_rls_after_cache_forget(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(TenantManager::class)->setTenantContext($tenantA->id);
        $this->seed(RolePermissionSeeder::class);
        Permission::create(['name' => 'tenant_a_special']);

        app(TenantManager::class)->setTenantContext($tenantB->id);
        $this->seed(RolePermissionSeeder::class);

        app(TenantManager::class)->setTenantContext($tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permsA = app(PermissionRegistrar::class)->getPermissions();
        $this->assertContains('tenant_a_special', $permsA->pluck('name'));

        app(TenantManager::class)->setTenantContext($tenantB->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permsB = app(PermissionRegistrar::class)->getPermissions();
        $this->assertNotContains('tenant_a_special', $permsB->pluck('name'));
    }
}
