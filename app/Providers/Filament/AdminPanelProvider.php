<?php

namespace App\Providers\Filament;

use App\Filament\Tenant\Pages\TenantDashboard;
use App\Filament\Tenant\Pages\TenantLogin;
use App\Filament\Tenant\Pages\TenantProductChangelogPage;
use App\Filament\Tenant\Widgets\StatsOverviewWidget;
use App\Http\Controllers\Filament\TenantSpatieMediaStreamController;
use App\Http\Controllers\Tenant\TenantNotificationBrowserController;
use App\Http\Controllers\Tenant\TenantNotificationPushSubscriptionController;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Middleware\FilamentTenantPanelAuthenticate;
use App\Http\Middleware\ResolveTenantFromDomain;
use App\Http\Middleware\SetTenantFilamentLocale;
use App\Models\TenantSetting;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => View::make('components.filament-access-denied-banner')->render())
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => View::make('components.scheduling-calendar-gating-banner')->render())
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => View::make('components.filament-tenant-storage-quota-banner')->render())
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite('resources/css/tenant-admin.css')"),
            );

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->profile()
            ->userMenuItems([
                Action::make('profile')
                    ->label('Профиль')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn (): string => Filament::getProfileUrl() ?? TenantDashboard::getUrl())
                    ->sort(-1),
                Action::make('whats_new')
                    ->label('Что нового')
                    ->icon('heroicon-o-newspaper')
                    ->url(fn (): string => TenantProductChangelogPage::getUrl())
                    ->sort(0),
            ])
            ->brandName(function (): string {
                $tenant = currentTenant();
                if ($tenant === null) {
                    return (string) config('app.name');
                }

                $name = trim((string) TenantSetting::getForTenant($tenant->id, 'general.site_name', ''));

                return $name !== '' ? $name : $tenant->defaultPublicSiteName();
            })
            ->homeUrl(function (): ?string {
                $tenant = currentTenant();
                if ($tenant === null) {
                    return null;
                }

                $stored = trim((string) TenantSetting::getForTenant($tenant->id, 'general.domain', ''));
                if ($stored !== '' && filter_var($stored, FILTER_VALIDATE_URL)) {
                    return $stored;
                }

                $fallback = $tenant->defaultPublicSiteUrl();
                if (filter_var($fallback, FILTER_VALIDATE_URL)) {
                    return $fallback;
                }

                return null;
            })
            ->login(TenantLogin::class)
            ->favicon(asset('favicon.svg'))
            ->globalSearch(false)
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                'Operations' => NavigationGroup::make()
                    ->label(self::tenantNavigationGroupLabel(DomainTermKeys::NAV_OPERATIONS, 'Операции'))
                    ->icon('heroicon-o-presentation-chart-line'),
                'Catalog' => NavigationGroup::make()
                    ->label(self::tenantNavigationGroupLabel(DomainTermKeys::NAV_CATALOG, 'Каталог'))
                    ->icon('heroicon-o-shopping-bag'),
                'Content' => NavigationGroup::make()
                    ->label(self::tenantNavigationGroupLabel(DomainTermKeys::NAV_CONTENT, 'Контент'))
                    ->icon('heroicon-o-document-text'),
                'Marketing' => NavigationGroup::make()
                    ->label(self::tenantNavigationGroupLabel(DomainTermKeys::NAV_MARKETING, 'Маркетинг'))
                    ->icon('heroicon-o-megaphone'),
                'Infrastructure' => NavigationGroup::make()
                    ->label(self::tenantNavigationGroupLabel(DomainTermKeys::NAV_INFRASTRUCTURE, 'Инфраструктура'))
                    ->icon('heroicon-o-server-stack'),
                'Settings' => NavigationGroup::make()
                    ->label(self::tenantNavigationGroupLabel(DomainTermKeys::NAV_SETTINGS, 'Настройки'))
                    ->icon('heroicon-o-cog-8-tooth'),
                'Scheduling' => NavigationGroup::make()
                    ->label('Запись и расписание')
                    ->icon('heroicon-o-calendar-days'),
            ])
            ->discoverResources(in: app_path('Filament/Tenant/Resources'), for: 'App\\Filament\\Tenant\\Resources')
            ->discoverPages(in: app_path('Filament/Tenant/Pages'), for: 'App\\Filament\\Tenant\\Pages')
            ->pages([
                TenantDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Tenant/Widgets'), for: 'App\\Filament\\Tenant\\Widgets')
            ->widgets([
                StatsOverviewWidget::class,
            ])
            ->middleware([
                ResolveTenantFromDomain::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetTenantFilamentLocale::class,
                ShareErrorsFromSession::class,
                AuthenticateSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                EnsureTenantContext::class,
                EnsureTenantMembership::class,
            ])
            ->authMiddleware([
                FilamentTenantPanelAuthenticate::class,
            ])
            /*
             * SPA (wire:navigate) отключён: после логина и на первом заходе в панель у части браузеров
             * интерфейс оставался «мёртвым» до полной перезагрузки (дубликат вкладки). См. обсуждения
             * filamentphp/filament вокруг SPA + Livewire navigate. Навигация — полными перезагрузками.
             */
            ->authenticatedRoutes(function (): void {
                Route::get('/spatie-media/{media}', [TenantSpatieMediaStreamController::class, 'show'])
                    ->name('spatie-media.show');
                Route::post('/notification-push/subscriptions', [TenantNotificationPushSubscriptionController::class, 'store'])
                    ->name('notification-push.subscriptions.store');
                Route::delete('/notification-push/subscriptions', [TenantNotificationPushSubscriptionController::class, 'destroy'])
                    ->name('notification-push.subscriptions.destroy');

                Route::prefix('notification-browser')->name('notification-browser.')->group(function (): void {
                    Route::get('vapid-public', [TenantNotificationBrowserController::class, 'vapidPublic'])->name('vapid-public');
                    Route::get('preferences', [TenantNotificationBrowserController::class, 'loadPreferences'])->name('preferences.show');
                    Route::put('preferences', [TenantNotificationBrowserController::class, 'savePreferences'])->name('preferences.save');
                    Route::get('crm-watermark', [TenantNotificationBrowserController::class, 'crmWatermark'])->name('crm-watermark');
                });
            });
    }

    /**
     * @return Closure(): string
     */
    private static function tenantNavigationGroupLabel(string $termKey, string $fallback): Closure
    {
        return function () use ($termKey, $fallback): string {
            $tenant = currentTenant();
            if ($tenant === null) {
                return $fallback;
            }

            return app(TenantTerminologyService::class)->label($tenant, $termKey);
        };
    }
}
