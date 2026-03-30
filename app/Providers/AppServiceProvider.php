<?php

namespace App\Providers;

use App\Auth\AccessRoles;
use App\Auth\TenantPivotPermissions;
use App\Jobs\Mail\SendTenantMailableJob;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\TenantSetting;
use App\Models\User;
use App\Observers\LeadObserver;
use App\Product\Mail\ProductMailOrchestrator;
use App\Product\Settings\MarketingContentResolver;
use App\Product\Settings\ProductMailSettingsResolver;
use App\Services\CurrentTenantManager;
use App\Services\Mail\TenantMailer;
use App\Services\Tenancy\TenantViewResolver;
use App\Terminology\TenantTerminologyService;
use Filament\Facades\Filament;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->singleton(TenantMailer::class);
        $this->app->singleton(MarketingContentResolver::class);
        $this->app->singleton(ProductMailSettingsResolver::class);
        $this->app->singleton(ProductMailOrchestrator::class);
        $this->app->singleton(TenantTerminologyService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Lead::observe(LeadObserver::class);

        RateLimiter::for('tenant-mails', function (mixed $job) {
            if (! $job instanceof SendTenantMailableJob) {
                return Limit::none();
            }

            $min = (int) config('mail_limits.min_per_minute', 1);
            $max = (int) config('mail_limits.max_per_minute', 1000);
            $n = max($min, min($max, $job->mailRateLimitPerMinute));

            return Limit::perMinute($n)->by('tenant-mail:'.$job->tenantId);
        });

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
                    'logo' => tenant_branding_logo_url(),
                    'primary_color' => TenantSetting::getForTenant($tenant->id, 'branding.primary_color', '#f59e0b'),
                    'favicon' => tenant_branding_favicon_url(),
                    'hero_image' => tenant_branding_hero_url(),
                ]);
                $view->with('site_name', TenantSetting::getForTenant($tenant->id, 'general.site_name', $tenant->defaultPublicSiteName()));
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
