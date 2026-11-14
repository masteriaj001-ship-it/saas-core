<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Plataforma\Services\ImpersonationService;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establece el contexto de tenant para rutas API y web tenant-scoped.
 *
 * El tenant se deriva siempre de la identidad autenticada; nunca de un
 * tenant_id, slug o cabecera enviados por el cliente.
 *
 * Superadmin:
 *  - web: solo con una sesión de impersonación activa y auditada
 *    (ImpersonationService); sin ella se deniega con 403.
 *  - API: se deniega; no existe selector de tenant autorizado en este corte.
 */
class SetRequestTenantContext
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ImpersonationService $impersonationService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'Unauthenticated.');
        }

        if ($user->is_superadmin) {
            return $this->handleSuperadmin($request, $next);
        }

        $tenantId = $user->fresh()?->tenant_id;

        if (empty($tenantId)) {
            abort(403, 'User has no tenant assignment.');
        }

        $this->applyContext((string) $tenantId);

        return $next($request);
    }

    private function handleSuperadmin(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            abort(403, 'Superadmin API access requires an explicit authorized tenant context.');
        }

        if (! $this->impersonationService->isImpersonating()) {
            abort(403, 'Superadmin access requires an active impersonation session.');
        }

        $tenantId = $this->impersonationService->getImpersonatingTenantId();

        if (empty($tenantId)) {
            abort(403, 'Superadmin access requires an active impersonation session.');
        }

        $this->applyContext((string) $tenantId);

        return $next($request);
    }

    private function applyContext(string $tenantId): void
    {
        try {
            $this->tenantManager->setTenantContext($tenantId);
        } catch (RuntimeException $exception) {
            abort(403, 'Invalid tenant context.');
        }
    }
}
