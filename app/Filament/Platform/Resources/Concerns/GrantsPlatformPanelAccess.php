<?php

namespace App\Filament\Platform\Resources\Concerns;

use App\Auth\AccessRoles;

trait GrantsPlatformPanelAccess
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(AccessRoles::platformRoles());
    }
}
