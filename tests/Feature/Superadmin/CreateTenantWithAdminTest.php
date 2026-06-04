<?php

declare(strict_types=1);

namespace Tests\Feature\Superadmin;

use App\Filament\Superadmin\Resources\TenantResource\Pages\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CreateTenantWithAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create(['is_superadmin' => true]);
        $this->actingAs($this->superadmin);

        Filament::setCurrentPanel(app('filament')->getPanel('superadmin'));

        // Seed roles/permissions under a temporary tenant context
        $seedTenant = Tenant::factory()->create();
        app(TenantManager::class)->setTenantContext($seedTenant->id);
        $this->seed(RolePermissionSeeder::class);
        app(TenantManager::class)->clearTenantContext();
    }

    public function test_superadmin_can_create_tenant_with_admin_user(): void
    {
        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Taller Test',
                'slug' => 'taller-test',
                'plan' => 'basic',
                'is_active' => true,
                'admin_name' => 'Admin User',
                'admin_email' => 'admin@test.com',
                'admin_password' => 'securePass1!',
                'admin_password_confirmation' => 'securePass1!',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tenants', [
            'name' => 'Taller Test',
            'slug' => 'taller-test',
            'plan' => 'basic',
        ]);

        $tenant = Tenant::where('slug', 'taller-test')->first();

        $this->assertDatabaseHas('users', [
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'tenant_id' => $tenant->id,
        ]);

        $user = User::where('email', 'admin@test.com')->first();
        $this->assertTrue($user->hasRole('owner'));
    }

    public function test_admin_user_can_login_after_tenant_creation(): void
    {
        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Taller Login',
                'slug' => 'taller-login',
                'plan' => 'basic',
                'is_active' => true,
                'admin_name' => 'Admin Login',
                'admin_email' => 'admin@login.com',
                'admin_password' => 'securePass1!',
                'admin_password_confirmation' => 'securePass1!',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = Tenant::where('slug', 'taller-login')->first();
        $user = User::where('email', 'admin@login.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('securePass1!', $user->password));

        $this->assertTrue($tenant->is_active);
        $this->assertTrue($tenant->onboarding_completed);

        // Verify the user can authenticate and access the tenant
        $this->assertTrue($user->canAccessTenant($tenant));
        $this->assertEquals($tenant->id, $user->tenant_id);
    }

    public function test_tenant_creation_is_atomic(): void
    {
        // First creation succeeds
        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Taller Atomic 1',
                'slug' => 'taller-atomic-1',
                'plan' => 'basic',
                'is_active' => true,
                'admin_name' => 'Atomic Admin',
                'admin_email' => 'atomic@test.com',
                'admin_password' => 'securePass1!',
                'admin_password_confirmation' => 'securePass1!',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Second attempt with same admin email must fail
        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Taller Atomic 2',
                'slug' => 'taller-atomic-2',
                'plan' => 'basic',
                'is_active' => true,
                'admin_name' => 'Atomic Admin 2',
                'admin_email' => 'atomic@test.com',
                'admin_password' => 'securePass1!',
                'admin_password_confirmation' => 'securePass1!',
            ])
            ->call('create')
            ->assertHasFormErrors(['admin_email' => 'unique']);

        // Second tenant must NOT exist
        $this->assertDatabaseMissing('tenants', [
            'slug' => 'taller-atomic-2',
        ]);
    }
}
