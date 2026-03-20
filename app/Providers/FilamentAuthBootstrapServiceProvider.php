<?php

namespace App\Providers;

use App\Filament\Auth\AppLoginResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as FilamentLoginResponseContract;
use Illuminate\Support\ServiceProvider;

/**
 * Filament auth wiring isolated for access-readiness (login redirect contract).
 */
class FilamentAuthBootstrapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FilamentLoginResponseContract::class, AppLoginResponse::class);
    }
}
