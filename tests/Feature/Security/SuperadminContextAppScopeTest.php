<?php

declare(strict_types=1);

/**
 * Application-level tests for GAP-002: Superadmin Tenant Context.
 *
 * These tests verify the SetTenantContext middleware behavior, the
 * withoutTenantContext() helper, and the CreateTenant context cleanup.
 * RLS is NOT enforced here — the default connection uses `sail` (BYPASSRLS=true).
 *
 * @see \Tests\Feature\Security\SuperadminContextRlsTest — RLS integration tests.
 */

namespace Tests\Feature\Security;

use App\Http\Middleware\SetTenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class SuperadminContextAppScopeTest extends TestCase
{
    use RefreshDatabase;

    private TenantManager $tenantManager;

    private Tenant $tenant;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->tenantManager = app(TenantManager::class);

        $this->tenant = Tenant::factory()->create(['is_active' => true]);

        $this->superadmin = User::factory()->create([
            'is_superadmin' => true,
            'tenant_id' => null,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tenantManager->clearTenantContext();
        parent::tearDown();
    }

    public function test_superadmin_sets_context_when_tenant_resolvable(): void
    {
        $this->actingAs($this->superadmin);

        $middleware = new class($this->tenantManager, $this->tenant) extends SetTenantContext
        {
            private Tenant $resolvedTenant;

            public function __construct(TenantManager $manager, Tenant $resolvedTenant)
            {
                parent::__construct($manager);
                $this->resolvedTenant = $resolvedTenant;
            }

            protected function resolveTenant(): ?Model
            {
                return $this->resolvedTenant;
            }
        };

        $request = Request::create('/admin/'.$this->tenant->slug.'/dashboard', 'GET');

        $middleware->handle($request, fn ($r) => response('ok'));

        $this->assertTrue($this->tenantManager->hasContext(), 'Superadmin should have tenant context when tenant is resolvable.');
        $this->assertEquals(
            $this->tenant->id,
            $this->tenantManager->getCurrentTenantId(),
            'Context should match the resolved tenant.'
        );
    }

    public function test_superadmin_skips_context_when_no_tenant_resolved(): void
    {
        $this->actingAs($this->superadmin);

        $middleware = new class($this->tenantManager) extends SetTenantContext
        {
            public function __construct(TenantManager $manager)
            {
                parent::__construct($manager);
            }

            protected function resolveTenant(): ?Model
            {
                return null;
            }
        };

        $request = Request::create('/superadmin', 'GET');

        $middleware->handle($request, fn ($r) => response('ok'));

        $this->assertFalse(
            $this->tenantManager->hasContext(),
            'Superadmin should NOT have tenant context when no tenant is resolvable (superadmin panel).'
        );
    }

    public function test_without_tenant_context_clears_and_restores(): void
    {
        $this->tenantManager->setTenantContext($this->tenant->id);
        $this->assertTrue($this->tenantManager->hasContext());
        $this->assertEquals($this->tenant->id, $this->tenantManager->getCurrentTenantId());

        $result = $this->tenantManager->withoutTenantContext(function () {
            $this->assertFalse($this->tenantManager->hasContext(), 'Context should be cleared inside withoutTenantContext.');

            return 'called';
        });

        $this->assertEquals('called', $result, 'Callback return value should be propagated.');

        $this->assertTrue($this->tenantManager->hasContext(), 'Context should be restored after withoutTenantContext.');
        $this->assertEquals(
            $this->tenant->id,
            $this->tenantManager->getCurrentTenantId(),
            'Original context should be restored.'
        );
    }

    public function test_without_tenant_context_without_prior_context(): void
    {
        $this->assertFalse($this->tenantManager->hasContext());

        $this->tenantManager->withoutTenantContext(function () {
            $this->assertFalse($this->tenantManager->hasContext(), 'Still no context inside withoutTenantContext.');
        });

        $this->assertFalse($this->tenantManager->hasContext(), 'Still no context after withoutTenantContext.');
    }

    public function test_create_tenant_clears_context_after_creation(): void
    {
        $this->actingAs($this->superadmin);

        $data = [
            'admin_name' => 'Admin User',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'SecurePass1!',
            'admin_password_confirmation' => 'SecurePass1!',
            'name' => 'New Tenant',
            'slug' => 'new-tenant',
            'plan' => 'free',
            'is_active' => true,
        ];

        $this->post('/superadmin/tenants', $data);

        $this->assertFalse(
            $this->tenantManager->hasContext(),
            'Tenant context should be cleared after tenant creation.'
        );
    }
}
