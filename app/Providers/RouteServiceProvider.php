<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * Dev routes (routes/dev.php) are loaded ONLY when the application
     * environment is 'local' or 'testing'. In any other environment,
     * the dev.php file is never loaded — requests to /dev/* return a
     * real 404, not a 403 authorization error.
     */
    public function boot(): void
    {
        if (app()->environment('local', 'testing')) {
            Route::middleware('api')
                ->prefix('dev')
                ->group(base_path('routes/dev.php'));
        }
    }
}
