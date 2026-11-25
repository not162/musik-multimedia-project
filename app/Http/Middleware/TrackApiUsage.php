<?php
/**
 * Phase 5: Track API usage for analytics and rate-limit monitoring.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrackApiUsage
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::channel('api')->info('API Request', [
            'method'   => $request->method(),
            'url'      => $request->fullUrl(),
            'status'   => $response->getStatusCode(),
            'duration' => $duration . 'ms',
            'ip'       => $request->ip(),
            'user_id'  => auth()->id(),
            'user_agent' => substr((string) $request->userAgent(), 0, 200),
        ]);

        $response->headers->set('X-Response-Time', $duration . 'ms');

        return $response;
    }
}
