<?php

namespace App\Providers;

use App\Services\InvitationEligibility;
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
        $this->app->singleton(InvitationEligibility::class, fn (): InvitationEligibility => new InvitationEligibility(
            enabled: (bool) config('rsvp.withdrawal_enabled')
                && config('rsvp.mode') === 'personal'
                && $this->app->environment(['local', 'testing']),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('cms-editor-upload', fn (Request $request) => [
            Limit::perMinute(15)->by('cms-upload:user:'.$request->user()?->id),
            Limit::perMinute(60)->by('cms-upload:ip:'.$request->ip()),
        ]);
        RateLimiter::for('cms-editor-read', fn (Request $request) => Limit::perMinute(240)->by('cms-read:'.$request->user()?->id));
        RateLimiter::for('cms-editor-validation', fn (Request $request) => Limit::perMinute(180)->by('cms-validation:'.$request->user()?->id));
        RateLimiter::for('cms-editor-save', fn (Request $request) => Limit::perMinute(30)->by('cms-save:'.$request->user()?->id));
        RateLimiter::for('invitation-access', fn (Request $request) => Limit::perMinute(60)->by('invitation:'.$request->ip()));
        RateLimiter::for('rsvp-setup', fn (Request $request) => Limit::perMinute(60)->by('rsvp-setup:'.$request->ip()));
        RateLimiter::for('rsvp-submissions', fn (Request $request) => [
            Limit::perMinute(120)->by('rsvp-ip:v2:'.$request->ip()),
            Limit::perMinute(20)->by('rsvp-link:v2:'.hash('sha256', (string) $request->route('token'))),
        ]);
        RateLimiter::for('personal-rsvp-read', fn (Request $request) => [
            Limit::perMinute(240)->by('personal-read-ip:'.$request->ip()),
            Limit::perMinute(60)->by('personal-read-link:'.hash('sha256', (string) $request->route('token'))),
        ]);
        RateLimiter::for('personal-rsvp-write', fn (Request $request) => [
            Limit::perMinute(120)->by('personal-write-ip:'.$request->ip()),
            Limit::perMinute(20)->by('personal-write-link:'.hash('sha256', (string) $request->route('token'))),
        ]);
        RateLimiter::for('personal-rsvp-owner', fn (Request $request) => Limit::perMinute(120)->by('personal-owner:'.$request->user()?->id.':'.$request->ip()));
    }
}
