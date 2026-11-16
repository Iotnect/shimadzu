<?php

namespace App\Providers;

use App\Auth\EnvironmentUserProvider;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        Auth::provider('environment', fn (Application $app, array $config): EnvironmentUserProvider => new EnvironmentUserProvider(
            (string) $config['email'],
            (string) $config['password_hash'],
        ));

        ResetPassword::createUrlUsing(fn (User $user, string $token): string => rtrim(config('app.url'), '/').route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ], absolute: false));

        RateLimiter::for('login', function (Request $request): Limit {
            $email = config('auth.providers.users.driver') === 'environment' ? null : $request->input('email');

            return Limit::perMinute(5)
                ->by(hash('sha256', (is_string($email) ? mb_strtolower($email) : '').'|'.$request->ip()))
                ->response(fn () => redirect()->route('login')->withErrors([
                    'email' => 'Too many sign-in attempts. Please try again in one minute.',
                ]));
        });
    }
}
