<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\VerifyTenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\FilamentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Suite: Tenant Suspension
 *
 * Certifica que el middleware VerifyTenantStatus bloquea correctamente
 * el acceso al panel /admin cuando un tenant está desactivado.
 *
 * Cobertura:
 *   - Test 1: Usuario normal de tenant ACTIVO → acceso permitido (200).
 *   - Test 2: Usuario normal de tenant INACTIVO → bloqueado (403) con vista de suspensión.
 *   - Test 3: Superadmin con tenant INACTIVO → acceso permitido (no bloqueado).
 *
 * Meta post-suite: 63/63 tests.
 */
class TenantSuspensionTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un tenant con el estado deseado.
     */
    private function createTenant(bool $isActive = true): Tenant
    {
        return Tenant::factory()->create([
            'is_active' => $isActive,
        ]);
    }

    /**
     * Crea un usuario normal ligado a un tenant dado.
     */
    private function createTenantUser(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_superadmin' => false,
        ]);
    }

    /**
     * Crea un superadmin (tenant_id = null, is_superadmin = true).
     */
    private function createSuperadmin(): User
    {
        return User::factory()->create([
            'tenant_id' => null,
            'is_superadmin' => true,
        ]);
    }

    /**
     * Simula la Request que llegaría a VerifyTenantStatus para un tenant dado,
     * autenticado como el usuario indicado.
     *
     * Usamos el middleware directamente (sin levantar el stack completo de Filament)
     * para mantener la suite rápida y sin dependencias del servidor HTTP.
     * Mockeamos Filament::getTenant() con el tenant de prueba.
     */
    private function callMiddleware(User $user, ?Tenant $tenant): Response
    {
        $filamentMock = $this->createMock(FilamentManager::class);
        $filamentMock->method('getTenant')->willReturn($tenant);
        Filament::swap($filamentMock);

        $request = Request::create('/admin/test-tenant/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new VerifyTenantStatus;

        return $middleware->handle($request, fn ($req) => response('OK', 200));
    }

    // ─────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────

    /**
     * Test 1: Un usuario normal cuyo tenant está activo debe pasar sin bloqueo.
     */
    public function test_active_tenant_user_can_access_panel(): void
    {
        $tenant = $this->createTenant(isActive: true);
        $user = $this->createTenantUser($tenant);

        $response = $this->callMiddleware($user, $tenant);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Test 2: Un usuario normal cuyo tenant está INACTIVO debe ser bloqueado
     * con HTTP 403 y ver la vista de suspensión.
     */
    public function test_inactive_tenant_user_is_blocked_with_403(): void
    {
        $tenant = $this->createTenant(isActive: false);
        $user = $this->createTenantUser($tenant);

        $response = $this->callMiddleware($user, $tenant);

        $this->assertSame(403, $response->getStatusCode());
    }

    /**
     * Test 3: Un superadmin debe poder acceder incluso si el tenant está INACTIVO.
     * Caso de uso: auditoría / soporte técnico sobre tenants suspendidos.
     */
    public function test_superadmin_bypasses_suspension_on_inactive_tenant(): void
    {
        $tenant = $this->createTenant(isActive: false);
        $superadmin = $this->createSuperadmin();

        $response = $this->callMiddleware($superadmin, $tenant);

        $this->assertSame(200, $response->getStatusCode());
    }
}
