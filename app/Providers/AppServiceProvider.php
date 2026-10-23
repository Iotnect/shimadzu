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
        RateLimiter::for('login', function (Request $request): Limit {
            $username = $request->input('username');

            return Limit::perMinute(5)
                ->by(hash('sha256', (is_string($username) ? mb_strtolower($username) : '').'|'.$request->ip()))
                ->response(fn () => redirect()->route('login')->withErrors([
                    'username' => 'Too many sign-in attempts. Please try again in one minute.',
                ]));
        });
    }
}
