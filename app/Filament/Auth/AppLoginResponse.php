<?php

namespace App\Filament\Auth;

use App\Auth\AccessRoles;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class AppLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $panel = Filament::getCurrentPanel();
        $user = Filament::auth()->user();

        if (
            $panel?->getId() === 'admin'
            && $user
            && $user->hasAnyRole(AccessRoles::platformRoles())
        ) {
            $platformUrl = Filament::getPanel('platform')->getUrl();

            return redirect()->intended($platformUrl);
        }

        return redirect()->intended(Filament::getUrl());
    }
}
