<?php

declare(strict_types=1);

/**
 * Application-level tests for the module activation system.
 *
 * These tests verify the Eloquent global scope and middleware behavior.
 * RLS is NOT enforced here — the default connection uses `sail` (BYPASSRLS=true).
 *
 * @see \Tests\Feature\Security\TenantModuleRlsTest — RLS integration tests.
 */

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class TenantModuleAppScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $this->tenant = Tenant::factory()->create([
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->clearTenantContext();
        parent::tearDown();
    }

    public function test_activate_module(): void
    {
        TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_slug' => 'taller',
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $this->assertDatabaseHas('tenant_modules', [
            'tenant_id' => $this->tenant->id,
            'module_slug' => 'taller',
            'is_active' => true,
        ]);

        $this->assertTrue($this->tenant->fresh()->hasModule('taller'));
    }

    public function test_inactive_module_returns_false(): void
    {
        TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_slug' => 'taller',
            'is_active' => false,
        ]);

        $this->assertFalse($this->tenant->fresh()->hasModule('taller'));
    }

    public function test_expired_module_returns_false(): void
    {
        TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_slug' => 'taller',
            'is_active' => true,
            'activated_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->tenant->fresh()->hasModule('taller'));
    }

    public function test_middleware_rejects_tenant_without_module(): void
    {
        $middleware = new EnsureModuleAccess;
        $request = Request::create('/test', 'GET');

        try {
            $middleware->handle($request, fn ($r) => response('ok'), 'taller');
            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    public function test_middleware_passes_with_active_module(): void
    {
        TenantModule::create([
            'tenant_id' => $this->tenant->id,
            'module_slug' => 'taller',
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $middleware = new EnsureModuleAccess;
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, fn ($r) => response('ok'), 'taller');

        $this->assertEquals('ok', $response->getContent());
    }
}
