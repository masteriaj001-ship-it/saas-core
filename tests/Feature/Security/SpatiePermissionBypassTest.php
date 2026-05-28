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

class SpatiePermissionBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_belongsto_tenant_scope_blocks_cross_tenant_roles_via_eloquent(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(TenantManager::class)->setTenantContext($tenantA->id);
        $this->seed(RolePermissionSeeder::class);

        app(TenantManager::class)->setTenantContext($tenantB->id);
        $this->seed(RolePermissionSeeder::class);
        $roleB = Role::where('name', 'owner')->first(); // tenant B's owner

        app(TenantManager::class)->setTenantContext($tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $found = Role::where('id', $roleB->id)->first();
        $this->assertNull($found);
    }

    public function test_belongsto_tenant_creating_event_prevents_orphan_roles(): void
    {
        $tenantA = Tenant::factory()->create();
        app(TenantManager::class)->clearTenantContext();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create');
        Role::create(['name' => 'orphan_role']);
    }

    public function test_belongsto_tenant_creating_event_prevents_orphan_permissions(): void
    {
        $tenantA = Tenant::factory()->create();
        app(TenantManager::class)->clearTenantContext();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create');
        \App\Models\Permission::create(['name' => 'orphan_perm']);
    }

    public function test_querying_roles_via_direct_sql_shows_all_but_scope_blocks(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(TenantManager::class)->setTenantContext($tenantA->id);
        $this->seed(RolePermissionSeeder::class);

        app(TenantManager::class)->setTenantContext($tenantB->id);
        $this->seed(RolePermissionSeeder::class);

        app(TenantManager::class)->setTenantContext($tenantA->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allViaSQL = DB::table('roles')->count();
        $scopedViaEloquent = Role::count();

        $this->assertEquals(8, $allViaSQL);
        $this->assertEquals(4, $scopedViaEloquent);
    }

    public function test_current_tenant_id_function_validates_uuid(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::statement("SELECT set_config('app.current_tenant_id', 'not-a-uuid', false)");
        DB::select('SELECT public.current_tenant_id()');
    }
}
