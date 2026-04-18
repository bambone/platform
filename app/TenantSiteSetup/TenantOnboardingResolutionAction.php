<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

enum TenantOnboardingResolutionAction: string
{
    case None = 'none';
    case PlatformEnablementRequired = 'platform_enablement_required';
    case UserPermissionGrant = 'user_permission_grant';
    case ClientAdjustExpectations = 'client_adjust_expectations';

    public function label(): string
    {
        return match ($this) {
            self::None => '',
            self::PlatformEnablementRequired => 'Обратитесь в поддержку RentBase, чтобы включить модуль записи для вашего аккаунта.',
            self::UserPermissionGrant => 'Выдайте соответствующую роль в команде клиента или продолжите под пользователем с доступом к записи.',
            self::ClientAdjustExpectations => 'Измените выбранный сценарий запуска на «Заявки и CRM», если запись по слотам не требуется.',
        };
    }
}
