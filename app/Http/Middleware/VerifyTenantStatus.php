<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso al panel /admin cuando el tenant tiene is_active = false.
 *
 * Posición en el pipeline: después de SetTenantContext (que inyecta el UUID en PG
 * y valida que el usuario tiene tenant_id), pero antes de que se ejecute cualquier
 * query de recursos.
 *
 * Excepción: los superadmins (is_superadmin = true) siempre pasan, ya que necesitan
 * poder auditar tenants suspendidos desde su panel o entrando en modo soporte.
 */
final class VerifyTenantStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        // Los superadmins tienen acceso irrestricto, incluso a tenants suspendidos.
        if ($request->user()?->is_superadmin) {
            return $next($request);
        }

        // Filament ya resolvió el Tenant desde {tenant:slug} en la URL.
        // Aprovechamos el modelo ya hidratado — sin query extra.
        $tenant = Filament::getTenant();

        if ($tenant !== null && ! $tenant->is_active) {
            return response()->view('errors.tenant-suspended', [], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
