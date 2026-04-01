<?php

namespace App\Providers\Filament;

use App\Filament\Tenant\Pages\Settings as TenantSettingsPage;
use App\Filament\Tenant\Pages\TenantDashboard;
use App\Filament\Tenant\Pages\TenantLogin;
use App\Filament\Tenant\Widgets\StatsOverviewWidget;
use App\Http\Controllers\Filament\TenantSpatieMediaStreamController;
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
                Action::make('tenant_site_settings')
                    ->label('Настройки сайта')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn (): string => TenantSettingsPage::getUrl())
                    ->visible(fn (): bool => TenantSettingsPage::canAccess())
                    ->sort(50),
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
            ->authenticatedRoutes(function (): void {
                Route::get('/spatie-media/{media}', [TenantSpatieMediaStreamController::class, 'show'])
                    ->name('spatie-media.show');
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
