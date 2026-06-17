<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_registration_form_is_accessible(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
        $response->assertSee('Crear cuenta');
    }

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'business_name' => 'Test Business',
            'industry' => 'general',
            'email' => 'test@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $response->assertRedirect('/admin');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue(Hash::check('SecurePass1!', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_creates_tenant(): void
    {
        $this->post(route('register'), [
            'name' => 'Jane Doe',
            'business_name' => 'Jane Business',
            'industry' => 'general',
            'email' => 'jane@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $user = User::where('email', 'jane@example.com')->first();

        $this->assertNotNull($user->tenant_id);
        $this->assertDatabaseHas('tenants', [
            'id' => $user->tenant_id,
        ]);
    }

    public function test_registration_assigns_owner_role(): void
    {
        $this->post(route('register'), [
            'name' => 'Owner User',
            'business_name' => 'Owner Business',
            'industry' => 'general',
            'email' => 'owner@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertTrue($user->hasRole('owner'));
    }

    public function test_registration_creates_role_permissions(): void
    {
        $this->post(route('register'), [
            'name' => 'Perm User',
            'business_name' => 'Perm Business',
            'industry' => 'general',
            'email' => 'perm@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $user = User::where('email', 'perm@example.com')->first();

        $this->assertTrue($user->hasAllPermissions([
            'create_work_orders', 'edit_work_orders', 'view_work_orders',
            'create_transactions', 'view_transactions',
        ]));
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantManager::class)->setTenantContext($tenant->id);

        $this->seed(RolePermissionSeeder::class);

        User::factory()->create([
            'email' => 'duplicate@example.com',
        ]);

        $response = $this->post(route('register'), [
            'name' => 'Another User',
            'business_name' => 'Duplicate Business',
            'industry' => 'general',
            'email' => 'duplicate@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_registration_fails_with_weak_password(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Weak Pass',
            'business_name' => 'Weak Business',
            'industry' => 'general',
            'email' => 'weak@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_registration_fails_without_uppercase(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'No Upper',
            'business_name' => 'NoUpper Business',
            'industry' => 'general',
            'email' => 'noupper@example.com',
            'password' => 'lowercase1!',
            'password_confirmation' => 'lowercase1!',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_registration_fails_without_number(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'No Number',
            'business_name' => 'NoNumber Business',
            'industry' => 'general',
            'email' => 'nonumber@example.com',
            'password' => 'UpperCase!',
            'password_confirmation' => 'UpperCase!',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_registration_fails_without_special_char(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'No Special',
            'business_name' => 'NoSpecial Business',
            'industry' => 'general',
            'email' => 'nospecial@example.com',
            'password' => 'Uppercase1',
            'password_confirmation' => 'Uppercase1',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_creates_unique_tenant_slug_per_registration(): void
    {
        $response1 = $this->post(route('register'), [
            'name' => 'Alpha',
            'business_name' => 'Alpha Corp',
            'industry' => 'general',
            'email' => 'alpha@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);
        $response1->assertRedirect('/admin');

        auth()->logout();
        app(TenantManager::class)->clearTenantContext();

        $response2 = $this->post(route('register'), [
            'name' => 'Beta',
            'business_name' => 'Beta Corp',
            'industry' => 'general',
            'email' => 'beta@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);
        $response2->assertSessionHasNoErrors();
        $response2->assertRedirect('/admin');

        $tenants = Tenant::all();
        $this->assertCount(2, $tenants);
        $this->assertNotEquals($tenants[0]->slug, $tenants[1]->slug);
    }

    public function test_registration_tenant_is_active_on_free_plan(): void
    {
        $this->post(route('register'), [
            'name' => 'Free User',
            'business_name' => 'Free Business',
            'industry' => 'general',
            'email' => 'free@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $user = User::where('email', 'free@example.com')->first();
        $tenant = Tenant::find($user->tenant_id);

        $this->assertTrue($tenant->is_active);
        $this->assertEquals('free', $tenant->plan);
    }
}
