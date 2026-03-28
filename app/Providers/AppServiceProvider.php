<?php

namespace App\Providers;

use App\Auth\AccessRoles;
use App\Auth\TenantPivotPermissions;
use App\Models\Setting;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\CurrentTenantManager;
use App\Services\Tenancy\TenantViewResolver;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentTenantManager::class);
        $this->app->singleton(TenantViewResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::anonymousComponentPath(resource_path('views/tenant/components'));

        Gate::before(function (?User $user, string $ability) {
            if ($user === null) {
                return null;
            }

            $panel = Filament::getCurrentPanel();
            if ($panel === null || $panel->getId() !== 'admin') {
                return null;
            }

            if (! str_starts_with($ability, 'manage_') && $ability !== 'export_leads') {
                return null;
            }

            $tenant = currentTenant();
            if ($tenant === null) {
                return null;
            }

            $membership = $user->tenants()->where('tenant_id', $tenant->id)->first();
            if ($membership === null || $membership->pivot->status !== 'active') {
                return false;
            }

            $role = $membership->pivot->role;
            if (! in_array($role, AccessRoles::tenantMembershipRolesForPanel(), true)) {
                return false;
            }

            return TenantPivotPermissions::pivotRoleAllows($role, $ability) ? true : false;
        });

        View::composer('*', function ($view) {
            $tenant = currentTenant();
            if ($tenant) {
                $view->with('contacts', [
                    'phone' => TenantSetting::getForTenant($tenant->id, 'contacts.phone', '+7 (913) 060-86-89'),
                    'phone_alt' => TenantSetting::getForTenant($tenant->id, 'contacts.phone_alt', ''),
                    'whatsapp' => preg_replace('/\D/', '', TenantSetting::getForTenant($tenant->id, 'contacts.whatsapp', '79130608689')),
                    'telegram' => ltrim(TenantSetting::getForTenant($tenant->id, 'contacts.telegram', 'motolevins'), '@'),
                ]);
                $view->with('branding', [
                    'logo' => TenantSetting::getForTenant($tenant->id, 'branding.logo', ''),
                    'primary_color' => TenantSetting::getForTenant($tenant->id, 'branding.primary_color', '#f59e0b'),
                    'favicon' => TenantSetting::getForTenant($tenant->id, 'branding.favicon', ''),
                ]);
                $view->with('site_name', TenantSetting::getForTenant($tenant->id, 'general.site_name', config('app.name')));
            } else {
                $view->with('contacts', [
                    'phone' => Setting::get('contacts.phone', '+7 (913) 060-86-89'),
                    'phone_alt' => Setting::get('contacts.phone_alt', ''),
                    'whatsapp' => preg_replace('/\D/', '', Setting::get('contacts.whatsapp', '79130608689')),
                    'telegram' => ltrim(Setting::get('contacts.telegram', 'motolevins'), '@'),
                ]);
                $view->with('branding', ['logo' => '', 'primary_color' => '#f59e0b', 'favicon' => '']);
                $view->with('site_name', config('app.name'));
            }
        });
    }
}
