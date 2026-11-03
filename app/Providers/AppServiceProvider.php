<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // The gold price lookup is cached and shared, so this only protects the endpoint itself.
        RateLimiter::for('gold-price', fn (Request $request) => Limit::perMinute((int) config('goldprice.throttle_per_minute'))
            ->by($request->user()?->id ?: $request->ip()));
    }
}
