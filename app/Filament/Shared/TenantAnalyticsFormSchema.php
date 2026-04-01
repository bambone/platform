<?php

namespace App\Filament\Shared;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\View;

/**
 * Shared "Аналитика" fields for tenant Settings and Platform TenantResource.
 * Form keys must stay in sync with App\Support\Analytics\AnalyticsSettingsFormMapper.
 */
final class TenantAnalyticsFormSchema
{
    /**
     * @return list<string>
     */
    public static function formFieldKeys(): array
    {
        return [
            'analytics_yandex_metrica_enabled',
            'analytics_yandex_counter_id',
            'analytics_yandex_webvisor_enabled',
            'analytics_yandex_clickmap_enabled',
            'analytics_yandex_track_links_enabled',
            'analytics_yandex_accurate_bounce_enabled',
            'analytics_ga4_enabled',
            'analytics_ga4_measurement_id',
        ];
    }

    /**
     * @param  Closure|bool  $visible  If false, section hidden (platform support must not see counter IDs).
     */
    public static function section(Closure|bool $visible = true): Section
    {
        $visibleClosure = $visible instanceof Closure
            ? $visible
            : fn (): bool => (bool) $visible;

        return Section::make('Аналитика')
            ->description('Подключение счётчиков только по ID. Не вставляйте код целиком — только идентификаторы.')
            ->visible($visibleClosure)
            ->headerActions([
                Action::make('analytics_help_yandex')
                    ->label('Как подключить Метрику')
                    ->link()
                    ->modalHeading('Яндекс Метрика: ID и настройки')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть')
                    ->modalWidth('2xl')
                    ->modalContent(fn () => View::make('filament.shared.analytics-help-yandex')),
                Action::make('analytics_help_ga4')
                    ->label('Как подключить GA4')
                    ->link()
                    ->modalHeading('Google Analytics 4: Measurement ID')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть')
                    ->modalWidth('2xl')
                    ->modalContent(fn () => View::make('filament.shared.analytics-help-ga4')),
            ])
            ->schema([
                Toggle::make('analytics_yandex_metrica_enabled')
                    ->label('Яндекс Метрика')
                    ->helperText('Включить официальный счётчик платформы (tag.js).'),
                TextInput::make('analytics_yandex_counter_id')
                    ->label('ID счётчика Метрики')
                    ->helperText('Укажите только ID счётчика, например 12345678. Не вставляйте код Метрики целиком.')
                    ->maxLength(32),
                Toggle::make('analytics_yandex_webvisor_enabled')
                    ->label('Вебвизор')
                    ->default(false),
                Toggle::make('analytics_yandex_clickmap_enabled')
                    ->label('Карта кликов')
                    ->default(false),
                Toggle::make('analytics_yandex_track_links_enabled')
                    ->label('Отслеживание ссылок')
                    ->default(false),
                Toggle::make('analytics_yandex_accurate_bounce_enabled')
                    ->label('Точный показатель отказов')
                    ->default(false),
                Toggle::make('analytics_ga4_enabled')
                    ->label('Google Analytics 4')
                    ->helperText('Включить gtag для GA4.'),
                TextInput::make('analytics_ga4_measurement_id')
                    ->label('Measurement ID (GA4)')
                    ->helperText('Укажите только Measurement ID, например G-ABC123DEF4. Не вставляйте gtag-код целиком.')
                    ->maxLength(32),
            ])->columns(2);
    }
}
