<?php

namespace App\Providers\Filament;

use App\Filament\Tenant\Pages\TenantDashboard;
use App\Filament\Tenant\Pages\TenantLogin;
use App\Filament\Tenant\Widgets\StatsOverviewWidget;
use App\Filament\Tenant\Widgets\TenantDashboardIntroWidget;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Middleware\FilamentTenantPanelAuthenticate;
use App\Http\Middleware\ResolveTenantFromDomain;
use App\Models\TenantSetting;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => View::make('components.filament-access-denied-banner')->render())
            ->renderHook(
                'panels::head.done',
                fn (): string => Blade::render("@vite('resources/css/tenant-admin.css')"),
            );

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
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
                NavigationGroup::make('Dashboard'),
                NavigationGroup::make('Operations')
                    ->label(function (): string {
                        $t = currentTenant();
                        if ($t === null) {
                            return 'Операции';
                        }

                        return app(TenantTerminologyService::class)->label($t, DomainTermKeys::NAV_OPERATIONS);
                    })
                    ->icon('heroicon-o-presentation-chart-line'),
                NavigationGroup::make('Catalog')
                    ->label(function (): string {
                        $t = currentTenant();
                        if ($t === null) {
                            return 'Каталог';
                        }

                        return app(TenantTerminologyService::class)->label($t, DomainTermKeys::NAV_CATALOG);
                    })
                    ->icon('heroicon-o-shopping-bag'),
                NavigationGroup::make('Content')
                    ->label(function (): string {
                        $t = currentTenant();
                        if ($t === null) {
                            return 'Контент';
                        }

                        return app(TenantTerminologyService::class)->label($t, DomainTermKeys::NAV_CONTENT);
                    })
                    ->icon('heroicon-o-document-text'),
                NavigationGroup::make('Settings')
                    ->label(function (): string {
                        $t = currentTenant();
                        if ($t === null) {
                            return 'Настройки';
                        }

                        return app(TenantTerminologyService::class)->label($t, DomainTermKeys::NAV_SETTINGS);
                    })
                    ->icon('heroicon-o-cog-8-tooth'),
            ])
            ->discoverResources(in: app_path('Filament/Tenant/Resources'), for: 'App\\Filament\\Tenant\\Resources')
            ->discoverPages(in: app_path('Filament/Tenant/Pages'), for: 'App\\Filament\\Tenant\\Pages')
            ->pages([
                TenantDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Tenant/Widgets'), for: 'App\\Filament\\Tenant\\Widgets')
            ->widgets([
                TenantDashboardIntroWidget::class,
                StatsOverviewWidget::class,
                AccountWidget::class,
            ])
            ->middleware([
                ResolveTenantFromDomain::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
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
            ]);
    }
}
