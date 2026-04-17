<?php

namespace App\Providers;

use App\Auth\AccessRoles;
use App\Auth\TenantPivotPermissions;
use App\ContactChannels\TenantPublicSiteContactsService;
use App\Filesystem\WindowsSafeFilesystem;
use App\Http\Controllers\HomeController;
use App\Jobs\Mail\SendTenantMailableJob;
use App\Jobs\PurgeSpatieMediaFromR2Job;
use App\Jobs\SyncSpatieMediaFolderToR2Job;
use App\Models\Faq;
use App\Models\Lead;
use App\Models\Motorcycle;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\RentalUnit;
use App\Models\Review;
use App\Models\Setting;
use App\Models\TenantSetting;
use App\Models\User;
use App\Money\MoneyAmountConverter;
use App\Money\MoneyFormatter;
use App\Money\MoneyParser;
use App\Money\TenantMoneySettingsResolver;
use App\NotificationCenter\NotificationActionUrlBuilder;
use App\NotificationCenter\NotificationChannelDriverFactory;
use App\NotificationCenter\NotificationDedupeService;
use App\NotificationCenter\NotificationDeliveryPlanner;
use App\NotificationCenter\NotificationEventRecorder;
use App\NotificationCenter\NotificationRouter;
use App\NotificationCenter\NotificationRuleDraftGenerator;
use App\NotificationCenter\NotificationSchedulePolicy;
use App\NotificationCenter\NotificationSubscriptionConditionEvaluator;
use App\Observers\LeadObserver;
use App\PageBuilder\LegacySectionTypeResolver;
use App\PageBuilder\PageSectionKeyGenerator;
use App\PageBuilder\PageSectionTypeRegistry;
use App\Product\Mail\ProductMailOrchestrator;
use App\Product\Settings\MarketingContentResolver;
use App\Product\Settings\ProductMailSettingsResolver;
use App\Scheduling\Calendar\CaldavCalendarProviderAdapter;
use App\Scheduling\Calendar\CalendarAdapterRegistry;
use App\Scheduling\Calendar\GoogleCalendarProviderAdapter;
use App\Scheduling\Calendar\NullCalendarProviderAdapter;
use App\Scheduling\LinkedBookableServiceManager;
use App\Services\CurrentTenantManager;
use App\Services\LinkPreview\ExternalArticlePreviewFetcher;
use App\Services\LinkPreview\ExternalArticlePreviewFetcherInterface;
use App\Services\Mail\TenantMailer;
use App\Services\PageBuilder\PageSectionOperationsService;
use App\Services\PageBuilder\SectionViewResolver;
use App\Services\Platform\PlatformNotificationSettings;
use App\Services\Tenancy\TenantAdvocateEditorialFooterData;
use App\Services\Tenancy\TenantExpertAutoFooterData;
use App\Services\Tenancy\TenantMainMenuPages;
use App\Services\Tenancy\TenantPagePrimaryHtmlSync;
use App\Services\Tenancy\TenantViewResolver;
use App\Support\TenantPanelMembershipCache;
use App\Tenant\Reviews\TenantReviewSubmitConfig;
use App\TenantPush\TenantPushCrmRequestRecipientResolver;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushNotificationBindingSync;
use App\TenantPush\TenantPushDiagnosticsService;
use App\TenantPush\TenantPushIosReadinessResolver;
use App\TenantPush\TenantPushOnesignalClient;
use App\Tenant\StorageQuota\TenantMediaStorageQuotaObserver;
use App\Terminology\TenantTerminologyService;
use App\Themes\ThemeRegistry;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Form as SchemaForm;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        $this->app->singleton(ExternalArticlePreviewFetcherInterface::class, ExternalArticlePreviewFetcher::class);

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
        $this->app->singleton(TenantPushFeatureGate::class);
        $this->app->singleton(TenantPushCrmRequestRecipientResolver::class);
        $this->app->singleton(TenantPushNotificationBindingSync::class);
        $this->app->singleton(TenantPushOnesignalClient::class);
        $this->app->singleton(TenantPushDiagnosticsService::class);
        $this->app->singleton(TenantPushIosReadinessResolver::class);
        $this->app->singleton(NotificationSchedulePolicy::class);
        $this->app->singleton(NotificationActionUrlBuilder::class);
        $this->app->singleton(NotificationDedupeService::class);
        $this->app->singleton(NotificationDeliveryPlanner::class);
        $this->app->singleton(NotificationSubscriptionConditionEvaluator::class);
        $this->app->singleton(NotificationRuleDraftGenerator::class);
        $this->app->singleton(NotificationRouter::class);
        $this->app->singleton(NotificationEventRecorder::class);
        $this->app->singleton(NotificationChannelDriverFactory::class);

        $this->app->singleton(CalendarAdapterRegistry::class, function ($app) {
            return new CalendarAdapterRegistry([
                $app->make(GoogleCalendarProviderAdapter::class),
                $app->make(CaldavCalendarProviderAdapter::class),
            ], $app->make(NullCalendarProviderAdapter::class));
        });

        $this->app->singleton(LinkedBookableServiceManager::class);

        $this->app->singleton(MoneyAmountConverter::class);
        $this->app->singleton(TenantMoneySettingsResolver::class);
        $this->app->singleton(MoneyFormatter::class, fn ($app) => new MoneyFormatter(
            $app->make(MoneyAmountConverter::class),
            $app->make(TenantMoneySettingsResolver::class),
        ));
        $this->app->singleton(MoneyParser::class, fn ($app) => new MoneyParser(
            $app->make(MoneyAmountConverter::class),
            $app->make(TenantMoneySettingsResolver::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        SchemaForm::configureUsing(
            fn (SchemaForm $form) => $form->extraAttributes(['novalidate' => true], merge: true),
        );

        // Filament FileUpload: дольше ждём завершения временной загрузки Livewire (по умолчанию 5 мин).
        config([
            'livewire.temporary_file_upload.max_upload_time' => max(
                (int) config('livewire.temporary_file_upload.max_upload_time', 5),
                15
            ),
        ]);

        Lead::observe(LeadObserver::class);
        Media::observe(TenantMediaStorageQuotaObserver::class);

        Media::saved(function (Media $media): void {
            if (! TenantMediaStorageQuotaObserver::shouldApply($media)) {
                return;
            }
            SyncSpatieMediaFolderToR2Job::dispatch((int) $media->getKey())->afterCommit();
        });
        Media::deleted(function (Media $media): void {
            if (! TenantMediaStorageQuotaObserver::shouldApply($media)) {
                return;
            }
            $tid = TenantMediaStorageQuotaObserver::tenantId($media);
            if ($tid === null) {
                return;
            }
            PurgeSpatieMediaFromR2Job::dispatch($tid, (int) $media->getKey())->afterCommit();
        });

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

        Motorcycle::saved(static function (Motorcycle $m): void {
            app(LinkedBookableServiceManager::class)->syncLinkedBookableFromMotorcycle($m);
        });

        RentalUnit::saved(static function (RentalUnit $unit): void {
            app(LinkedBookableServiceManager::class)->syncLinkedBookableFromRentalUnit($unit);
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

            $membership = TenantPanelMembershipCache::membershipFor(request(), $user, $tenant);
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

            if (self::shouldSkipTenantPublicLayoutBundle($request)) {
                $bundle = self::tenantPublicLayoutPlaceholderBundle();
                $request->attributes->set($attrKey, $bundle);
                $view->with($bundle);

                return;
            }

            $tenant = currentTenant();
            if ($tenant) {
                $publicContactsService = app(TenantPublicSiteContactsService::class);
                $publicContacts = $publicContactsService->contactsForPublicLayout($tenant);
                $bundle = [
                    'contacts' => $publicContacts,
                    'floating_messenger_buttons_enabled' => $publicContactsService->floatingMessengerButtonsEnabled((int) $tenant->id),
                    'branding' => [
                        'logo' => tenant_branding_logo_url(),
                        'primary_color' => TenantSetting::getForTenant($tenant->id, 'branding.primary_color', '#f59e0b'),
                        'favicon' => tenant_branding_favicon_url(),
                        'hero_image' => tenant_branding_hero_url(),
                        'favicon_16' => tenant_site_brand_optional_public_url('favicon-16.png'),
                        'favicon_32' => tenant_site_brand_optional_public_url('favicon-32.png'),
                        'favicon_ico' => tenant_site_brand_optional_public_url('favicon.ico'),
                        'apple_touch_icon' => tenant_site_brand_optional_public_url('apple-touch-icon.png'),
                    ],
                    'site_name' => TenantSetting::getForTenant($tenant->id, 'general.site_name', $tenant->defaultPublicSiteName()),
                    'tenantMainMenuPages' => app(TenantMainMenuPages::class)->menuItems($tenant),
                    'tenantAdvocateFooter' => $tenant->themeKey() === 'advocate_editorial'
                        ? app(TenantAdvocateEditorialFooterData::class)->build($tenant)
                        : null,
                    'tenantExpertAutoFooter' => $tenant->themeKey() === 'expert_auto'
                        ? app(TenantExpertAutoFooterData::class)->build($tenant)
                        : null,
                    'tenantReviewSubmitConfig' => TenantReviewSubmitConfig::forTenant((int) $tenant->id),
                ];
            } else {
                $bundle = [
                    'contacts' => [
                        'phone' => Setting::get('contacts.phone', ''),
                        'phone_alt' => Setting::get('contacts.phone_alt', ''),
                        'email' => Setting::get('contacts.email', ''),
                        'whatsapp' => preg_replace('/\D/', '', Setting::get('contacts.whatsapp', '')),
                        'telegram' => ltrim((string) Setting::get('contacts.telegram', ''), '@'),
                        'vk_url' => '',
                    ],
                    'floating_messenger_buttons_enabled' => true,
                    'branding' => [
                        'logo' => '',
                        'primary_color' => '#f59e0b',
                        'favicon' => '',
                        'hero_image' => '',
                        'favicon_16' => '',
                        'favicon_32' => '',
                        'favicon_ico' => '',
                        'apple_touch_icon' => '',
                    ],
                    'site_name' => config('app.name'),
                    'tenantMainMenuPages' => collect(),
                    'tenantAdvocateFooter' => null,
                    'tenantExpertAutoFooter' => null,
                    'tenantReviewSubmitConfig' => null,
                ];
            }

            $request->attributes->set($attrKey, $bundle);
            $view->with($bundle);
        });
    }

    /**
     * Кабинет Filament / Livewire: не собираем бандл публичного лейаута (меню сайта, футер, контакты, обзоры).
     */
    private static function shouldSkipTenantPublicLayoutBundle(Request $request): bool
    {
        $routeName = $request->route()?->getName();
        if (is_string($routeName) && str_starts_with($routeName, 'filament.')) {
            return true;
        }

        $host = strtolower($request->getHost());
        $platform = strtolower(trim((string) config('app.platform_host', '')));
        if ($platform !== '' && $host === $platform) {
            return true;
        }

        $path = trim($request->path(), '/');
        if ($path === 'admin' || str_starts_with($path, 'admin/')) {
            return true;
        }

        if ($request->is('livewire/*')) {
            $referer = $request->headers->get('referer');
            if (is_string($referer) && preg_match('#/admin(?:/|$|\?)#', $referer) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function tenantPublicLayoutPlaceholderBundle(): array
    {
        return [
            'contacts' => [
                'phone' => '',
                'phone_alt' => '',
                'email' => '',
                'whatsapp' => '',
                'telegram' => '',
                'vk_url' => '',
            ],
            'floating_messenger_buttons_enabled' => false,
            'branding' => [
                'logo' => '',
                'primary_color' => '#f59e0b',
                'favicon' => '',
                'hero_image' => '',
                'favicon_16' => '',
                'favicon_32' => '',
                'favicon_ico' => '',
                'apple_touch_icon' => '',
            ],
            'site_name' => config('app.name'),
            'tenantMainMenuPages' => collect(),
            'tenantAdvocateFooter' => null,
            'tenantExpertAutoFooter' => null,
            'tenantReviewSubmitConfig' => null,
        ];
    }
}
