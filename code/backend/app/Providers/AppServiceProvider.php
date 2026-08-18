<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            if (app()->environment('local', 'testing')) {
                return Limit::none();
            }

            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            if ($request->getHost() === '127.0.0.1' || app()->environment('local', 'testing')) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        if (app()->environment('production') && blank(env('FRONTEND_URL'))) {
            throw new \RuntimeException('FRONTEND_URL é obrigatório em production (CORS).');
        }

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
