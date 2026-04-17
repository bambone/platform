<?php

declare(strict_types=1);

namespace App\TenantPush;

enum TenantPushSubscriptionAggregate: string
{
    case None = 'none';
    case Partial = 'partial';
    case Active = 'active';

    public function platformLabel(): string
    {
        return match ($this) {
            self::None => 'Нет',
            self::Partial => 'Частично',
            self::Active => 'Активны',
        };
    }

    public function filamentBadgeColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Partial => 'warning',
            self::Active => 'success',
        };
    }
}
