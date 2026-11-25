<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\{
    CacheResponse,
    CheckAccountLock,
    EnsureAdmin,
    ForceHttps,
    LoadBalancerHeaders,
    SecurityHeaders,
    TrackApiUsage,
};
use App\Services\ErrorTrackerService;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Phase 7: Trust reverse proxies / load balancers (Nginx, Cloudflare, AWS ALB)
        $middleware->trustProxies(at: '*');

        // Phase 4 & 7: Global security & load-balancer node headers
        $middleware->append([
            SecurityHeaders::class,
            ForceHttps::class,
            LoadBalancerHeaders::class,
        ]);

        // Phase 3: Web session account lock / active check
        $middleware->appendToGroup('web', CheckAccountLock::class);

        // Phase 5: API usage tracking
        $middleware->appendToGroup('api', [
            TrackApiUsage::class,
        ]);

        // Middleware aliases for granular route control
        $middleware->alias([
            'admin'          => EnsureAdmin::class,
            'active.user'    => CheckAccountLock::class,
            'cache.response' => CacheResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Phase 9: Log unhandled exceptions to error_logs & broadcast alert to Admin role
        $exceptions->report(function (\Throwable $e) {
            if (! app()->runningUnitTests()) {
                ErrorTrackerService::capture($e, 'error', 'exception');
            }
        });
    })->create();
