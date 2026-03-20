<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MotorcycleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\EnsureTenantContext;
use Illuminate\Support\Facades\Route;

$platformHost = config('app.platform_host');
if (is_string($platformHost) && $platformHost !== '') {
    Route::domain($platformHost)->group(function () {
        Route::view('/', 'platform.home')->name('platform.home');
        Route::view('/features', 'platform.features')->name('platform.features');
        Route::view('/pricing', 'platform.pricing')->name('platform.pricing');
        Route::view('/for-moto-rental', 'platform.for-moto-rental')->name('platform.for-moto-rental');
        Route::view('/for-car-rental', 'platform.for-car-rental')->name('platform.for-car-rental');
        Route::view('/faq', 'platform.faq')->name('platform.faq');
        Route::view('/contact', 'platform.contact')->name('platform.contact');
    });
}

Route::middleware([EnsureTenantContext::class])->group(function () {
    Route::get('/robots.txt', RobotsController::class)->name('robots');
    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::view('/offline', 'offline')->name('offline');
    Route::view('/contacts', 'pages.contacts')->name('contacts');
    Route::view('/usloviya-arenda', 'pages.terms')->name('terms');
    Route::view('/motorcycles', 'pages.motorcycles.index')->name('motorcycles.index');
    Route::view('/prices', 'pages.prices')->name('prices');
    Route::view('/order', 'pages.order')->name('order');
    Route::view('/reviews', 'pages.reviews')->name('reviews');
    Route::view('/faq', 'pages.faq')->name('faq');
    Route::view('/about', 'pages.about')->name('about');
    Route::view('/delivery/anapa', 'pages.delivery.anapa')->name('delivery.anapa');
    Route::view('/delivery/gelendzhik', 'pages.delivery.gelendzhik')->name('delivery.gelendzhik');
    Route::get('/moto/{slug}', [MotorcycleController::class, 'show'])->name('motorcycle.show');

    // Public booking flow
    Route::get('/booking', [PublicBookingController::class, 'index'])->name('booking.index');
    Route::get('/booking/moto/{slug}', [PublicBookingController::class, 'show'])->name('booking.show');
    Route::post('/booking/calculate', [PublicBookingController::class, 'calculate'])->name('booking.calculate');
    Route::post('/booking/draft', [PublicBookingController::class, 'storeDraft'])->name('booking.store-draft');
    Route::get('/checkout', [PublicBookingController::class, 'checkout'])->name('booking.checkout');
    Route::post('/checkout', [PublicBookingController::class, 'storeCheckout'])->name('booking.store-checkout');
    Route::get('/thank-you/{booking?}', [PublicBookingController::class, 'thankYou'])->name('booking.thank-you');
    Route::view('/articles', 'pages.articles.index')->name('articles.index');
    Route::get('/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('page.show');
    Route::post('/api/bookings', [BookingController::class, 'store'])->name('api.bookings.store');
    Route::post('/api/leads', [LeadController::class, 'store'])->name('api.leads.store');
});
