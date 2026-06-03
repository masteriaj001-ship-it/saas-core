<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->is_superadmin) {
            abort(403, __('Acceso denegado. Se requieren permisos de superadministrador.'));
        }

        return $next($request);
    }
}
