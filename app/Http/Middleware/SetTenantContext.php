<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(
        private readonly TenantManager $tenantManager
    ) {}

    public function handle(Request $request, \Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        if ($user->is_superadmin) {
            $tenant = $this->resolveTenant();

            if ($tenant !== null) {
                $this->tenantManager->setTenantContext((string) $tenant->id);
            }

            return $next($request);
        }

        $tenantId = $user->fresh()->tenant_id;

        if (empty($tenantId)) {
            abort(403, 'User has no tenant assignment.');
        }

        $urlTenant = Filament::getTenant();

        if ($urlTenant && $urlTenant->id !== $tenantId) {
            abort(403, 'Access denied to this tenant.');
        }

        $this->tenantManager->setTenantContext((string) $tenantId);

        return $next($request);
    }

    protected function resolveTenant(): ?Model
    {
        return Filament::getTenant();
    }
}
