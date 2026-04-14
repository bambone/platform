<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
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
        return Gate::allows('manage_notifications') || Gate::allows('manage_notification_subscriptions');
    }
}
