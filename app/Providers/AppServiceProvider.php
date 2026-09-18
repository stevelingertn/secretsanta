<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
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
        // Stored in UTC, shown in the show's local time.
        Carbon::macro('inShowTz', function () {
            /** @var Carbon $this */
            return $this->copy()->setTimezone(config('app.display_timezone'));
        });

        // Login codes: ~49.5 bits of entropy plus these limits make guessing impractical.
        RateLimiter::for('contestant-login', fn (Request $request) => [
            Limit::perMinute(10)->by('code-min:'.$request->ip()),
            Limit::perHour(40)->by('code-hour:'.$request->ip()),
        ]);
        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinute(5)->by('admin:'.$request->ip()),
            Limit::perMinute(5)->by('admin-user:'.strtolower((string) $request->input('username'))),
        ]);
        RateLimiter::for('ballot', fn (Request $request) => Limit::perMinute(30)->by('ballot:'.($request->user()?->id ?? $request->ip())));

        Paginator::defaultView('pagination::tailwind');
    }
}
