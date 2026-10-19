<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Needed for older MySQL versions (index length limit)
        Schema::defaultStringLength(191);

        // Login: max 5 attempts per minute for the same username from the same IP.
        // This stops password guessing.
        RateLimiter::for('login', function (Request $request) {
            $username = Str::lower((string) $request->input('username'));

            return Limit::perMinute(5)->by($username . '|' . $request->ip());
        });
    }
}