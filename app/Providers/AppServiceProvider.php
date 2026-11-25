<?php
/**
 * Phase 5: Rate Limiting Configuration
 * Phase 6: Cache & CDN Helpers
 * Phase 7: SEO Service Provider
 * Phase 9: Error Tracking & Admin Alert Badge
 */

namespace App\Providers;

use App\Models\{ErrorLog, SeoMetadata};
use App\Services\SeoService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{RateLimiter, View};
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ── Phase 5: Rate limit tiers ────────────────────────────────
        RateLimiter::for('api-auth', function (Request $request) {
            return Limit::perMinute((int) env('RATE_LIMIT_API_AUTH', 10))
                ->by($request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Terlalu banyak permintaan autentikasi. Tunggu sebentar.',
                ], 429));
        });

        RateLimiter::for('api-user', function (Request $request) {
            return Limit::perMinute((int) env('RATE_LIMIT_API_USER', 60))
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn () => response()->json([
                    'message'     => 'Rate limit tercapai. Coba lagi nanti.',
                    'retry_after' => 60,
                ], 429));
        });

        RateLimiter::for('api-admin', function (Request $request) {
            return Limit::perMinute((int) env('RATE_LIMIT_API_ADMIN', 120))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-public', function (Request $request) {
            return Limit::perMinute((int) env('RATE_LIMIT_API_PUBLIC', 60))
                ->by($request->ip())
                ->response(fn () => response()->json(['message' => 'Rate limit publik tercapai.'], 429));
        });

        // ── Phase 7 & 9: Share SEO meta + Admin Unresolved Error Count ─
        View::composer('layouts.musikku', function ($view) {
            $routeName = request()->route()?->getName();
            $view->with('seo', SeoService::resolve($routeName));

            if (auth()->check() && auth()->user()->isAdmin()) {
                try {
                    $view->with('unresolvedErrorsCount', ErrorLog::unresolved()->count());
                } catch (\Throwable) {
                    $view->with('unresolvedErrorsCount', 0);
                }
            }
        });
    }
}
