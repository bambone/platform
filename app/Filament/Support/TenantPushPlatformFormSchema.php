<?php

namespace App\Filament\Support;

use App\TenantPush\TenantPushOverride;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

/**
 * Поля {@see TenantPushSettings}, редактируемые с карточки клиента в панели платформы (не колонки tenants).
 */
final class TenantPushPlatformFormSchema
{
    /**
     * @return list<string>
     */
    public static function formFieldKeys(): array
    {
        return [
            'platform_push_override',
            'platform_push_commercial_active',
            'platform_push_self_serve_allowed',
        ];
    }

    public static function section(): Section
    {
        return Section::make('Push и PWA (платформа)')
            ->description('Доступ к разделу «PWA и Push» в кабинете клиента. Включите функцию в тарифе (Платформа → Тарифы → «OneSignal Web Push…») или задайте «Принудительно включить» ниже (обход проверки тарифа/коммерции). Редактирование ключей в кабинете задаётся только переключателем «Клиент может сам менять…».')
            ->visibleOn('edit')
            ->schema([
                Select::make('platform_push_override')
                    ->label('Переопределение тарифа')
                    ->options([
                        TenantPushOverride::InheritPlan->value => 'Как в тарифе',
                        TenantPushOverride::ForceEnable->value => 'Принудительно включить (обход тарифа)',
                        TenantPushOverride::ForceDisable->value => 'Принудительно выключить',
                    ])
                    ->required()
                    ->native(true)
                    ->helperText('Даёт доступ к функции по смыслу тарифа без смены тарифа; не открывает сохранение в кабинете, если выключено «Клиент может сам менять…».'),
                Toggle::make('platform_push_commercial_active')
                    ->label('Услуга Push активирована (коммерция)')
                    ->helperText('Пока выключено, функция недоступна при схеме «тариф + коммерция», даже если функция есть в тарифе.'),
                Toggle::make('platform_push_self_serve_allowed')
                    ->label('Клиент может сам менять настройки OneSignal и PWA')
                    ->helperText('Если выключено, клиент видит раздел без возможности сохранить настройки OneSignal/PWA, даже при «Принудительно включить».'),
            ])
            ->columns(1);
    }
}
