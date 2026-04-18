<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

enum TenantOnboardingBranchConsistency: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Blocked = 'blocked';
    case NeedsPlatformAction = 'needs_platform_action';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Сценарий согласован с возможностями аккаунта',
            self::Warning => 'Есть ограничения — проверьте подсказку',
            self::Blocked => 'Продолжение выбранного сценария сейчас недоступно',
            self::NeedsPlatformAction => 'Нужно действие платформы (включение модуля или тарифа)',
        };
    }
}
