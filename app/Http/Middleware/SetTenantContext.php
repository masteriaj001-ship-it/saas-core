<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class SetTenantContext
{
    public function __construct(
        private readonly TenantManager $tenantManager
    ) {}

    public function handle(Request $request, \Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $tenantId = $user->tenant_id;

        if (empty($tenantId)) {
            abort(403, 'User has no tenant assignment.');
        }

        $this->tenantManager->setTenantContext((string) $tenantId);

        try {
            $response = $next($request);
        } finally {
            $this->tenantManager->clearTenantContext();
        }

        return $response;
    }
}
