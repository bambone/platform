<?php

namespace App\Providers\Filament;

use App\Filament\Platform\Pages\PlatformDashboard;
use App\Filament\Platform\Resources\PlatformProductChangelogEntryResource;
use App\Filament\Platform\Widgets\PlatformActivityWidget;
use App\Filament\Platform\Widgets\PlatformDashboardIntroWidget;
use App\Filament\Platform\Widgets\PlatformStatsWidget;
use App\Http\Middleware\EnsurePlatformAccess;
use Filament\Actions\Action;
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
use Illuminate\View\ComponentAttributeBag;
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
            ->userMenuItems([
                Action::make('product_changelog')
                    ->label('Чейнджлог продукта')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(fn (): string => PlatformProductChangelogEntryResource::getUrl())
                    ->sort(15),
            ])
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => View::make('components.filament-access-denied-banner')->render())
            ->brandName('RentBase Platform')
            ->brandLogo(fn () => View::make('components.platform-logo', [
                'attributes' => new ComponentAttributeBag,
            ]))
            ->favicon(asset('favicon.svg'))
            ->globalSearch(false)
            ->font('Inter')
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite('resources/css/platform-admin.css')"),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => config('app.debug_filament_platform_overlay')
                    ? Blade::render("@vite('resources/js/platform-admin-overlay-diagnostics.js')")
                    : '',
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
