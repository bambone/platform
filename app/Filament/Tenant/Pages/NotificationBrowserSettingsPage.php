<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Support\TenantPanelHintHeaderAction;
use Filament\Pages\Page;
use UnitEnum;

class NotificationBrowserSettingsPage extends Page
{
    protected static ?string $navigationLabel = 'Браузер (push и звук)';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-speaker-wave';

    protected static ?string $title = 'Браузер и уведомления';

    protected static ?string $slug = 'notification-subscriptions/browser';

    protected static ?int $navigationSort = 25;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected string $view = 'filament.tenant.pages.notification-browser-settings';

    public static function canAccess(): bool
    {
        // Личные настройки браузера (push/звук в кабинете) — любой участник клиента с доступом в панель.
        return currentTenant() !== null;
    }

    protected function getHeaderActions(): array
    {
        return [
            TenantPanelHintHeaderAction::makeLines(
                'notificationBrowserWhatIs',
                [
                    'Разрешения браузера на звук и web push для уведомлений в этом кабинете.',
                    '',
                    'Правила email, Telegram и т.д. настраиваются отдельно в центре уведомлений.',
                ],
                'Справка по браузерным уведомлениям',
            ),
        ];
    }
}
