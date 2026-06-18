<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = $request->user();

        if ($user?->user_type === 'client') {
            return redirect()->to(Filament::getUrl());
        }

        return redirect()->intended(Filament::getUrl());
    }
}
