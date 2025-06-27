<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;

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
        // Set default string length for older MySQL versions
        Schema::defaultStringLength(191);

        // Use Bootstrap for pagination
        Paginator::useBootstrapFive();

        // Force HTTPS in production
        if (config('app.env') === 'production') {
            \URL::forceScheme('https');
        }
    }
}
