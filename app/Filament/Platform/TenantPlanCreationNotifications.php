<?php

namespace App\Filament\Platform;

use Filament\Notifications\Notification;

/**
 * Единая копия текстов для сценариев «нельзя создать клиента без активного тарифа»
 * (мастер онбординга и ручное создание в TenantResource).
 */
final class TenantPlanCreationNotifications
{
    public static function noActivePlans(): Notification
    {
        return Notification::make()
            ->title('Нет активного тарифа')
            ->body('В системе нет ни одного активного тарифа. Включите тариф в разделе «Тарифы» платформы и повторите создание клиента.')
            ->danger()
            ->persistent();
    }

    public static function selectedPlanInactive(): Notification
    {
        return Notification::make()
            ->title('Тариф недоступен')
            ->body('Выберите активный тариф или включите выбранный тариф в разделе «Тарифы».')
            ->danger()
            ->persistent();
    }
}
