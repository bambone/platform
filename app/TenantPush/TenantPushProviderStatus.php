<?php

declare(strict_types=1);

namespace App\TenantPush;

enum TenantPushProviderStatus: string
{
    case NotConfigured = 'not_configured';
    case Invalid = 'invalid';
    case Verified = 'verified';

    public function platformLabel(): string
    {
        return match ($this) {
            self::NotConfigured => 'Не настроен',
            self::Invalid => 'Ошибка ключей',
            self::Verified => 'Проверен',
        };
    }

    public function filamentBadgeColor(): string
    {
        return match ($this) {
            self::NotConfigured => 'gray',
            self::Invalid => 'danger',
            self::Verified => 'success',
        };
    }
}
