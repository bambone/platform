<?php

namespace App\Filament\Auth;

use App\Auth\AccessRoles;
use App\Models\PlatformSetting;
use App\Models\User;
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

        $preferTenantPanel = (bool) PlatformSetting::get('tenant_login_prefer_tenant_panel', false);

        if (
            $panel?->getId() === 'admin'
            && $user instanceof User
            && $user->hasAnyRole(AccessRoles::platformRoles())
            && ! $preferTenantPanel
        ) {
            $platformUrl = Filament::getPanel('platform')->getUrl();

            return redirect()->intended($platformUrl);
        }

        return redirect()->intended(Filament::getUrl());
    }
}
