<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MotorcycleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PlatformLlmsTxtController;
use App\Http\Controllers\PlatformMarketingContactController;
use App\Http\Controllers\PlatformMarketingRobotsController;
use App\Http\Controllers\PlatformMarketingSitemapController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TenantLlmsTxtController;
use App\Http\Controllers\TenantPublicFaqController;
use App\Http\Controllers\TenantPublicBookingAvailabilityController;
use App\Http\Controllers\TenantPublicPageController;
use App\Http\Controllers\TenantPublicStorageFileController;
use App\Http\Controllers\ThemePlatformAssetController;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\ResolveTenantPublicSeo;
use App\Models\TenantDomain;
use Illuminate\Support\Facades\Route;

$marketingHosts = [];
foreach (config('tenancy.central_domains', []) as $h) {
    $normalized = TenantDomain::normalizeHost((string) $h);
    if ($normalized !== '' && ! in_array($normalized, $marketingHosts, true)) {
        $marketingHosts[] = $normalized;
    }
}

// РљРѕСЂРµРЅСЊ PLATFORM_HOST РѕР±СЂР°Р±Р°С‚С‹РІР°РµС‚ Filament (РіРѕСЃС‚СЊ в†’ /login, РїРѕСЃР»Рµ РІС…РѕРґР° вЂ” РґРѕРјР°С€РЅСЏСЏ СЃС‚СЂР°РЅРёС†Р° РїР°РЅРµР»Рё).

if ($marketingHosts !== []) {
    foreach ($marketingHosts as $index => $domain) {
        Route::domain($domain)->group(function () use ($index) {
            $named = $index === 0;

            if ($named) {
                Route::get('/robots.txt', PlatformMarketingRobotsController::class)->name('platform.marketing.robots');
                Route::get('/sitemap.xml', PlatformMarketingSitemapController::class)->name('platform.marketing.sitemap');
                Route::get('/llms.txt', PlatformLlmsTxtController::class)->name('platform.marketing.llms');
                Route::view('/', 'platform.marketing.home')->name('platform.home');
                Route::view('/features', 'platform.marketing.features')->name('platform.features');
                Route::view('/pricing', 'platform.marketing.pricing')->name('platform.pricing');
                Route::get('/for-moto-rental', fn () => view('platform.marketing.segment-landing', ['segmentKey' => 'moto']))->name('platform.for-moto-rental');
                Route::get('/for-car-rental', fn () => view('platform.marketing.segment-landing', ['segmentKey' => 'car']))->name('platform.for-car-rental');
                Route::get('/for-services', fn () => view('platform.marketing.segment-landing', ['segmentKey' => 'services']))->name('platform.for-services');
                Route::view('/faq', 'platform.marketing.faq')->name('platform.faq');
                Route::view('/contact', 'platform.marketing.contact')->name('platform.contact');
                Route::post('/contact', [PlatformMarketingContactController::class, 'store'])
                    ->middleware('throttle:15,1')
                    ->name('platform.contact.store');
            } else {
                Route::get('/robots.txt', PlatformMarketingRobotsController::class);
                Route::get('/sitemap.xml', PlatformMarketingSitemapController::class);
                Route::get('/llms.txt', PlatformLlmsTxtController::class);
                Route::view('/', 'platform.marketing.home');
                Route::view('/features', 'platform.marketing.features');
                Route::view('/pricing', 'platform.marketing.pricing');
                Route::get('/for-moto-rental', fn () => view('platform.marketing.segment-landing', ['segmentKey' => 'moto']));
                Route::get('/for-car-rental', fn () => view('platform.marketing.segment-landing', ['segmentKey' => 'car']));
                Route::get('/for-services', fn () => view('platform.marketing.segment-landing', ['segmentKey' => 'services']));
                Route::view('/faq', 'platform.marketing.faq');
                Route::view('/contact', 'platform.marketing.contact');
                Route::post('/contact', [PlatformMarketingContactController::class, 'store'])
                    ->middleware('throttle:15,1');
            }
        });
    }
}

Route::middleware([EnsureTenantContext::class, ResolveTenantPublicSeo::class])->group(function () {
    Route::get('/theme/build/{theme}/{path}', [ThemePlatformAssetController::class, 'show'])
        ->where('path', '.*')
        ->name('theme.platform.asset');
    Route::get('/storage/tenants/{tenantId}/public/{path}', [TenantPublicStorageFileController::class, 'show'])
        ->where('tenantId', '[0-9]+')
        ->where('path', '.*')
        ->name('tenant.public.storage');
    Route::get('/robots.txt', RobotsController::class)->name('robots');
    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
    Route::get('/llms.txt', TenantLlmsTxtController::class)->name('llms');
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::view('/offline', 'tenant.pages.offline')->name('offline');
    Route::get('/contacts', [PageController::class, 'show'])
        ->defaults('slug', 'contacts')
        ->name('contacts');
    Route::get('/usloviya-arenda', [PageController::class, 'show'])
        ->defaults('slug', 'usloviya-arenda')
        ->name('terms');
    Route::get('/motorcycles', [MotorcycleController::class, 'catalogIndex'])->name('motorcycles.index');
    Route::view('/prices', 'tenant.pages.prices')->name('prices');
    Route::view('/order', 'tenant.pages.order')->name('order');
    Route::view('/reviews', 'tenant.pages.reviews')->name('reviews');
    Route::get('/faq', TenantPublicFaqController::class)->name('faq');
    Route::get('/about', [TenantPublicPageController::class, 'show'])
        ->defaults('logical', 'pages.about')
        ->name('about');
    Route::view('/delivery/anapa', 'tenant.pages.delivery.anapa')->name('delivery.anapa');
    Route::view('/delivery/gelendzhik', 'tenant.pages.delivery.gelendzhik')->name('delivery.gelendzhik');
    Route::get('/moto/{slug}', [MotorcycleController::class, 'show'])->name('motorcycle.show');

    // Public booking flow
    Route::get('/booking', [PublicBookingController::class, 'index'])->name('booking.index');
    Route::get('/booking/moto/{slug}', [PublicBookingController::class, 'show'])->name('booking.show');
    Route::post('/booking/calculate', [PublicBookingController::class, 'calculate'])->name('booking.calculate');
    Route::post('/booking/draft', [PublicBookingController::class, 'storeDraft'])->name('booking.store-draft');
    Route::get('/checkout', [PublicBookingController::class, 'checkout'])->name('booking.checkout');
    Route::post('/checkout', [PublicBookingController::class, 'storeCheckout'])->name('booking.store-checkout');
    Route::get('/thank-you/{booking?}', [PublicBookingController::class, 'thankYou'])->name('booking.thank-you');
    Route::view('/articles', 'tenant.pages.articles.index')->name('articles.index');
    Route::post('/api/tenant/booking/catalog-availability', [TenantPublicBookingAvailabilityController::class, 'catalogAvailability'])
        ->middleware('throttle:120,1')
        ->name('api.tenant.booking.catalog-availability');
    Route::post('/api/tenant/booking/motorcycle-calendar-hints', [TenantPublicBookingAvailabilityController::class, 'motorcycleCalendarHints'])
        ->middleware('throttle:120,1')
        ->name('api.tenant.booking.motorcycle-calendar-hints');
    Route::post('/api/bookings', [BookingController::class, 'store'])->name('api.bookings.store');
    Route::post('/api/leads', [LeadController::class, 'store'])->name('api.leads.store');
    Route::get('/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('page.show');
});
