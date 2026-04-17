<?php

declare(strict_types=1);

namespace App\TenantPush;

enum TenantPushRecipientScope: string
{
    case OwnerOnly = 'owner_only';
    case SelectedUsers = 'selected_users';
    case AllAdmins = 'all_admins';
}
