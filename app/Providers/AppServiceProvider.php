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
        RateLimiter::for('invitation-access', fn (Request $request) => Limit::perMinute(60)->by('invitation:'.$request->ip()));
        RateLimiter::for('rsvp-setup', fn (Request $request) => Limit::perMinute(60)->by('rsvp-setup:'.$request->ip()));
        RateLimiter::for('rsvp-submissions', fn (Request $request) => [
            Limit::perMinute(120)->by('rsvp-ip:v2:'.$request->ip()),
            Limit::perMinute(20)->by('rsvp-link:v2:'.hash('sha256', (string) $request->route('token'))),
        ]);
    }
}
