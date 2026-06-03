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

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private TenantManager $tenantManager;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantManager = app(TenantManager::class);
        $this->tenant = Tenant::factory()->create();
        $this->tenantManager->setTenantContext($this->tenant->id);

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create([
            'email' => 'api@example.com',
            'password' => Hash::make('SecurePass1!'),
        ]);
    }

    public function test_can_create_api_token(): void
    {
        $response = $this->postJson('/api/sanctum/token', [
            'email' => 'api@example.com',
            'password' => 'SecurePass1!',
            'device_name' => 'Test Device',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'abilities']);
        $this->assertNotNull($response->json('token'));
    }

    public function test_can_create_token_with_custom_abilities(): void
    {
        $response = $this->postJson('/api/sanctum/token', [
            'email' => 'api@example.com',
            'password' => 'SecurePass1!',
            'device_name' => 'Limited Device',
            'abilities' => ['view_transactions'],
        ]);

        $response->assertOk();
        $this->assertEquals(['view_transactions'], $response->json('abilities'));
    }

    public function test_create_token_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/sanctum/token', [
            'email' => 'api@example.com',
            'password' => 'WrongPass1!',
            'device_name' => 'Bad Device',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_token_has_user_association(): void
    {
        $this->postJson('/api/sanctum/token', [
            'email' => 'api@example.com',
            'password' => 'SecurePass1!',
            'device_name' => 'Test Device',
        ]);

        $tokenId = $this->user->tokens()->first()->id;

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'tokenable_id' => $this->user->id,
            'name' => 'Test Device',
        ]);
    }

    public function test_can_revoke_current_token(): void
    {
        $token = $this->user->createToken('Revocable');
        $tokenId = $token->accessToken->id;

        $response = $this->withToken($token->plainTextToken)
            ->deleteJson('/api/sanctum/token');

        $response->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_can_revoke_all_tokens(): void
    {
        $this->user->createToken('Token A');
        $this->user->createToken('Token B');
        $tokenC = $this->user->createToken('Token C');

        $response = $this->withToken($tokenC->plainTextToken)
            ->deleteJson('/api/sanctum/tokens');

        $response->assertOk();
        $this->assertEquals(0, $this->user->tokens()->count());
    }

    public function test_can_list_tokens(): void
    {
        $this->user->createToken('Visible Token');
        $token = $this->user->createToken('Current Token');

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/sanctum/tokens');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_unauthenticated_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/sanctum/tokens');

        $response->assertStatus(401);
    }
}
