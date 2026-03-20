<?php

namespace App\Filament\Platform\Pages\Concerns;

use App\Auth\AccessRoles;

trait GrantsPlatformPageAccess
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(AccessRoles::platformRoles()) ?? false;
    }
}
