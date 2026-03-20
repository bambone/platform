<?php

namespace App\Filament\Platform\Pages;

use UnitEnum;

class BillingPage extends PlatformPlaceholderPage
{
    protected static ?string $navigationLabel = 'Биллинг';

    protected static ?string $title = 'Биллинг';

    protected static ?string $slug = 'billing';

    protected static ?string $panel = 'platform';

    protected static string|UnitEnum|null $navigationGroup = 'Платформа';

    protected static function placeholderMeta(): array
    {
        return [
            'headline' => 'Биллинг и оплата',
            'intro' => 'Здесь будет сводка по подпискам и оплатам клиентов платформы: тарифы, счета, статусы оплаты.',
            'future' => 'Счета, история платежей, напоминания об оплате и связка с платёжными провайдерами — по мере подключения биллинга.',
            'audience' => 'Владелец платформы и финансовые роли.',
        ];
    }
}
