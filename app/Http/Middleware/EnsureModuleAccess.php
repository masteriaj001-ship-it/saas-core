<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenantId = app(TenantManager::class)->getCurrentTenantId();

        if (! $tenantId) {
            abort(403, __('No tenant context available.'));
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant || ! $tenant->hasModule($module)) {
            abort(403, __('El módulo ":module" no está activo en tu plan.', ['module' => $module]));
        }

        return $next($request);
    }
}
