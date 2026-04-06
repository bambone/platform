<?php

namespace App\Providers;

use App\Auth\AccessRoles;
use App\Auth\TenantPivotPermissions;
use App\Filesystem\WindowsSafeFilesystem;
use App\Http\Controllers\HomeController;
use App\Jobs\Mail\SendTenantMailableJob;
use App\Models\Faq;
use App\Models\Lead;
use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Review;
use App\Models\Setting;
use App\Models\TenantSetting;
use App\Models\User;
use App\NotificationCenter\NotificationActionUrlBuilder;
use App\NotificationCenter\NotificationChannelDriverFactory;
use App\NotificationCenter\NotificationDedupeService;
use App\NotificationCenter\NotificationDeliveryPlanner;
use App\NotificationCenter\NotificationEventRecorder;
use App\NotificationCenter\NotificationRouter;
use App\NotificationCenter\NotificationSchedulePolicy;
use App\Observers\LeadObserver;
use App\PageBuilder\LegacySectionTypeResolver;
use App\PageBuilder\PageSectionKeyGenerator;
use App\PageBuilder\PageSectionTypeRegistry;
use App\Product\Mail\ProductMailOrchestrator;
use App\Product\Settings\MarketingContentResolver;
use App\Product\Settings\ProductMailSettingsResolver;
use App\Services\CurrentTenantManager;
use App\Services\Mail\TenantMailer;
use App\Services\PageBuilder\PageSectionOperationsService;
use App\Services\PageBuilder\SectionViewResolver;
use App\Services\Platform\PlatformNotificationSettings;
use App\Services\Tenancy\TenantMainMenuPages;
use App\Services\Tenancy\TenantPagePrimaryHtmlSync;
use App\Services\Tenancy\TenantViewResolver;
use App\Tenant\StorageQuota\TenantMediaStorageQuotaObserver;
use App\Terminology\TenantTerminologyService;
use App\Themes\ThemeRegistry;
use Filament\Facades\Filament;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /* До boot(): иначе blade.compiler может получить стандартный Filesystem до подмены. */
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->app->singleton('files', fn () => new WindowsSafeFilesystem);
        }

        $this->app->singleton(CurrentTenantManager::class);
        $this->app->singleton(TenantViewResolver::class);
        $this->app->singleton(TenantMailer::class);
        $this->app->singleton(MarketingContentResolver::class);
        $this->app->singleton(ProductMailSettingsResolver::class);
        $this->app->singleton(ProductMailOrchestrator::class);
        $this->app->singleton(TenantTerminologyService::class);
        $this->app->singleton(ThemeRegistry::class);
        $this->app->singleton(TenantMainMenuPages::class);
        $this->app->singleton(TenantPagePrimaryHtmlSync::class);
        $this->app->singleton(PageSectionTypeRegistry::class);
        $this->app->singleton(LegacySectionTypeResolver::class);
        $this->app->singleton(PageSectionKeyGenerator::class);
        $this->app->singleton(PageSectionOperationsService::class);
        $this->app->singleton(SectionViewResolver::class);

        $this->app->singleton(PlatformNotificationSettings::class);
        $this->app->singleton(NotificationSchedulePolicy::class);
        $this->app->singleton(NotificationActionUrlBuilder::class);
        $this->app->singleton(NotificationDedupeService::class);
        $this->app->singleton(NotificationDeliveryPlanner::class);
        $this->app->singleton(NotificationRouter::class);
        $this->app->singleton(NotificationEventRecorder::class);
        $this->app->singleton(NotificationChannelDriverFactory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Filament FileUpload: дольше ждём завершения временной загрузки Livewire (по умолчанию 5 мин).
        config([
            'livewire.temporary_file_upload.max_upload_time' => max(
                (int) config('livewire.temporary_file_upload.max_upload_time', 5),
                15
            ),
        ]);

        Lead::observe(LeadObserver::class);
        Media::observe(TenantMediaStorageQuotaObserver::class);

        $forgetTenantHome = static function (int $tenantId): void {
            if ($tenantId > 0) {
                HomeController::forgetCachedPayloadForTenant($tenantId);
            }
        };

        Page::saved(static function (Page $page) use ($forgetTenantHome): void {
            if ($page->slug === 'home' && $page->tenant_id) {
                $forgetTenantHome((int) $page->tenant_id);
            }
        });
        Page::deleted(static function (Page $page) use ($forgetTenantHome): void {
            if ($page->slug === 'home' && $page->tenant_id) {
                $forgetTenantHome((int) $page->tenant_id);
            }
        });

        $forgetIfHomeSection = static function (PageSection $section) use ($forgetTenantHome): void {
            if (! $section->tenant_id) {
                return;
            }
            $section->loadMissing('page');
            if ($section->page && $section->page->slug === 'home') {
                $forgetTenantHome((int) $section->tenant_id);
            }
        };
        PageSection::saved($forgetIfHomeSection);
        PageSection::deleted($forgetIfHomeSection);

        Motorcycle::saved(static function (Motorcycle $m) use ($forgetTenantHome): void {
            if ($m->tenant_id) {
                $forgetTenantHome((int) $m->tenant_id);
            }
        });
        Motorcycle::deleted(static function (Motorcycle $m) use ($forgetTenantHome): void {
            if ($m->tenant_id) {
                $forgetTenantHome((int) $m->tenant_id);
            }
        });

        Faq::saved(static function (Faq $faq) use ($forgetTenantHome): void {
            if ($faq->tenant_id) {
                $forgetTenantHome((int) $faq->tenant_id);
            }
        });

        Review::saved(static function (Review $review) use ($forgetTenantHome): void {
            if ($review->tenant_id) {
                $forgetTenantHome((int) $review->tenant_id);
            }
        });

        $forgetHomeIfMotorcycleMedia = static function (Media $media) use ($forgetTenantHome): void {
            if ($media->model_type !== Motorcycle::class || $media->model_id === null || $media->model_id === '') {
                return;
            }
            $tid = Motorcycle::query()->whereKey((int) $media->model_id)->value('tenant_id');
            if ($tid) {
                $forgetTenantHome((int) $tid);
            }
        };
        Media::saved($forgetHomeIfMotorcycleMedia);
        Media::deleted($forgetHomeIfMotorcycleMedia);

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

            if (! str_starts_with($ability, 'manage_') && ! in_array($ability, ['export_leads', 'view_notification_history'], true)) {
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

        // Composer on '*' runs for every Blade view (layout, @include, anonymous components).
        // Build shared vars once per request: avoids repeated TenantSetting/cache + disk->exists on branding paths.
        View::composer('*', function ($view) {
            $request = request();
            $attrKey = '_tenant_public_layout_vars';
            if ($request->attributes->has($attrKey)) {
                $view->with($request->attributes->get($attrKey));

                return;
            }

            $tenant = currentTenant();
            if ($tenant) {
                $bundle = [
                    'contacts' => [
                        'phone' => TenantSetting::getForTenant($tenant->id, 'contacts.phone', '+7 (913) 060-86-89'),
                        'phone_alt' => TenantSetting::getForTenant($tenant->id, 'contacts.phone_alt', ''),
                        'whatsapp' => preg_replace('/\D/', '', TenantSetting::getForTenant($tenant->id, 'contacts.whatsapp', '79130608689')),
                        'telegram' => ltrim(TenantSetting::getForTenant($tenant->id, 'contacts.telegram', 'motolevins'), '@'),
                    ],
                    'branding' => [
                        'logo' => tenant_branding_logo_url(),
                        'primary_color' => TenantSetting::getForTenant($tenant->id, 'branding.primary_color', '#f59e0b'),
                        'favicon' => tenant_branding_favicon_url(),
                        'hero_image' => tenant_branding_hero_url(),
                    ],
                    'site_name' => TenantSetting::getForTenant($tenant->id, 'general.site_name', $tenant->defaultPublicSiteName()),
                    'tenantMainMenuPages' => app(TenantMainMenuPages::class)->menuItems($tenant),
                ];
            } else {
                $bundle = [
                    'contacts' => [
                        'phone' => Setting::get('contacts.phone', '+7 (913) 060-86-89'),
                        'phone_alt' => Setting::get('contacts.phone_alt', ''),
                        'whatsapp' => preg_replace('/\D/', '', Setting::get('contacts.whatsapp', '79130608689')),
                        'telegram' => ltrim(Setting::get('contacts.telegram', 'motolevins'), '@'),
                    ],
                    'branding' => ['logo' => '', 'primary_color' => '#f59e0b', 'favicon' => '', 'hero_image' => ''],
                    'site_name' => config('app.name'),
                    'tenantMainMenuPages' => collect(),
                ];
            }

            $request->attributes->set($attrKey, $bundle);
            $view->with($bundle);
        });
    }
}
