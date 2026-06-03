<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingIsCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return $next($request);
        }

        $isOnboardingPage = $request->routeIs('filament.admin.pages.onboarding');

        if (! $tenant->onboarding_completed && ! $isOnboardingPage) {
            return redirect()->to(route('filament.admin.pages.onboarding', ['tenant' => $tenant->slug]));
        }

        if ($tenant->onboarding_completed && $isOnboardingPage) {
            return redirect()->to(route('filament.admin.pages.dashboard', ['tenant' => $tenant->slug]));
        }

        return $next($request);
    }
}
