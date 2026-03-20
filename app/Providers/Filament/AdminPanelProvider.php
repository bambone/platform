<?php

namespace App\Providers\Filament;

use App\Filament\Tenant\Widgets\StatsOverviewWidget;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Middleware\ResolveTenantFromDomain;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
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
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->renderHook(PanelsRenderHook::STYLES_AFTER, function (): string {
                return Blade::render(<<<'HTML'
                    <style>
                        .fi-main-ctn { flex: 1 !important; min-width: 0 !important; }
                        .fi-main { max-width: none !important; width: 100% !important; }
                    </style>
                HTML);
            })
            ->renderHook(PanelsRenderHook::TOPBAR_AFTER, function (): string {
                $tenant = \currentTenant();
                if (! $tenant) {
                    return '';
                }
                $label = $tenant->brand_name ?: $tenant->name;

                return Blade::render(
                    <<<'HTML'
                    <div class="fi-tenant-context hidden sm:flex items-center me-4 text-sm font-medium text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        <span class="opacity-70 me-1">Tenant:</span>
                        <span class="text-gray-950 dark:text-white">{{ $label }}</span>
                    </div>
                    HTML,
                    ['label' => $label]
                );
            });

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->globalSearch(false)
            ->maxContentWidth('full')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Tenant/Resources'), for: 'App\\Filament\\Tenant\\Resources')
            ->discoverPages(in: app_path('Filament/Tenant/Pages'), for: 'App\\Filament\\Tenant\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Tenant/Widgets'), for: 'App\\Filament\\Tenant\\Widgets')
            ->widgets([
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
                Authenticate::class,
            ]);
    }
}
