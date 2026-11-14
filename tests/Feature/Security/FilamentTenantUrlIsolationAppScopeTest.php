<?php

declare(strict_types=1);

/**
 * Application-level tenant isolation for the Filament tenant URL.
 *
 * Runs over the default `pgsql` connection: proves IdentifyTenant /
 * canAccessTenant reject a manipulated {tenant:slug}. It does NOT prove that
 * PostgreSQL RLS policies were applied.
 */

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FilamentTenantUrlIsolationAppScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->tenantA = Tenant::factory()->create([
            'name' => 'Filament Tenant A',
            'onboarding_completed' => true,
            'is_active' => true,
        ]);
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Filament Tenant B',
            'onboarding_completed' => true,
            'is_active' => true,
        ]);

        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'is_superadmin' => false,
        ]);

        $this->superadmin = User::factory()->create([
            'tenant_id' => null,
            'is_superadmin' => true,
        ]);
    }

    public function test_manipulated_tenant_slug_is_rejected(): void
    {
        $this->actingAs($this->userA);

        $this->get("/admin/{$this->tenantB->slug}")->assertStatus(404);
    }

    public function test_own_tenant_slug_remains_accessible(): void
    {
        $this->actingAs($this->userA);

        $this->get("/admin/{$this->tenantA->slug}")->assertStatus(200);
    }

    public function test_unknown_tenant_slug_is_rejected(): void
    {
        $this->actingAs($this->userA);

        $this->get('/admin/tenant-que-no-existe')->assertStatus(404);
    }

    public function test_superadmin_can_open_any_tenant_url(): void
    {
        $this->actingAs($this->superadmin);

        $this->get("/admin/{$this->tenantB->slug}")->assertStatus(200);
    }

    public function test_regular_user_cannot_open_superadmin_panel(): void
    {
        $this->actingAs($this->userA);

        $this->get('/superadmin')->assertStatus(403);
    }
}
