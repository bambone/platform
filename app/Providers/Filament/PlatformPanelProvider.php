<?php

namespace App\Providers\Filament;

use App\Filament\Platform\Pages\PlatformDashboard;
use App\Filament\Platform\Widgets\PlatformActivityWidget;
use App\Filament\Platform\Widgets\PlatformDashboardIntroWidget;
use App\Filament\Platform\Widgets\PlatformStatsWidget;
use App\Http\Middleware\EnsurePlatformAccess;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PlatformPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('platform')
            ->path('')
            ->domain(config('app.platform_host', 'platform.rentbase.local'))
            ->login()
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => View::make('components.filament-access-denied-banner')->render())
            ->brandName('Platform Console')
            ->globalSearch(false)
            ->font('Inter')
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(
                'panels::head.done',
                fn (): string => Blade::render("@vite('resources/css/platform-admin.css')"),
            )
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Platform')
                    ->icon('heroicon-o-server-stack'),
                NavigationGroup::make()
                    ->label('Клиенты')
                    ->icon('heroicon-o-building-office'),
                NavigationGroup::make()
                    ->label('CRM')
                    ->icon('heroicon-o-users'),
                NavigationGroup::make()
                    ->label('Почта')
                    ->icon('heroicon-o-envelope'),
                NavigationGroup::make()
                    ->label('Система')
                    ->icon('heroicon-o-cog-8-tooth'),
            ])
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Platform/Resources'), for: 'App\\Filament\\Platform\\Resources')
            ->discoverPages(in: app_path('Filament/Platform/Pages'), for: 'App\\Filament\\Platform\\Pages')
            ->pages([
                PlatformDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Platform/Widgets'), for: 'App\\Filament\\Platform\\Widgets')
            ->widgets([
                PlatformDashboardIntroWidget::class,
                PlatformStatsWidget::class,
                PlatformActivityWidget::class,
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                AuthenticateSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                EnsurePlatformAccess::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
