<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

enum TenantOnboardingBranchId: string
{
    case CrmOnly = 'crm_only';
    case SlotBooking = 'slot_booking';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::CrmOnly => 'Заявки и CRM (без обязательной онлайн-записи по слотам)',
            self::SlotBooking => 'Онлайн-запись и бронирование по слотам',
            self::Mixed => 'И заявки в CRM, и запись по слотам (оба сценария)',
        };
    }

    public static function tryFromMvp(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
