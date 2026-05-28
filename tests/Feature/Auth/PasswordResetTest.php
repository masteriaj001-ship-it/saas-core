<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private TenantManager $tenantManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantManager = app(TenantManager::class);
        $this->tenant = Tenant::factory()->create();
        $this->tenantManager->setTenantContext($this->tenant->id);

        Notification::fake();
    }

    public function test_forgot_password_form_is_accessible(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertSee('Recuperar contraseña');
    }

    public function test_sends_reset_link_email(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'reset@example.com',
        ]);

        $response->assertSessionHas('status');
        $response->assertRedirect();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_does_not_send_reset_link_for_unknown_email(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => 'unknown@example.com',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_reset_password_form_is_accessible(): void
    {
        $response = $this->get(route('password.reset', ['token' => 'fake-token']));

        $response->assertStatus(200);
        $response->assertSee('Restablecer contraseña');
    }

    public function test_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-full@example.com',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'reset-full@example.com',
            'password' => 'NewSecure1!',
            'password_confirmation' => 'NewSecure1!',
        ]);

        $response->assertSessionHas('status');
        $response->assertRedirect();

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecure1!', $user->password));
    }

    public function test_reset_fails_with_weak_password(): void
    {
        $user = User::factory()->create([
            'email' => 'weak-reset@example.com',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'weak-reset@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'badtoken@example.com',
        ]);

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token-12345',
            'email' => 'badtoken@example.com',
            'password' => 'NewSecure1!',
            'password_confirmation' => 'NewSecure1!',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
