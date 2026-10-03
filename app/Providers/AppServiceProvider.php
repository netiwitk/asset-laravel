<?php

namespace App\Providers;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Plain CSS on Filament's hook classes; `php artisan filament:assets` publishes it to public/css/app.
        FilamentAsset::register([
            Css::make('asset-theme', resource_path('css/filament/asset-theme.css')),
        ]);
    }
}
