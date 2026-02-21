<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Configure email rate limiting: 2 emails per second (Resend.com limit)
        RateLimiter::for('emails', function ($job) {
            return Limit::perSecond(2)->by('email-sending');
        });
    }
}
