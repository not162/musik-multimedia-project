<?php

namespace App\Http\Middleware;

use App\Models\ApiEndpointRule;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, RateLimiter};

/**
 * Enforces dynamic API Management endpoint toggles & rate-limit rules
 * configured by Admin in real-time.
 */
class EnforceApiRule
{
    public function handle(Request $request, Closure $next, string $ruleKey)
    {
        $rule = Cache::remember("api_rule:{$ruleKey}", 60, fn () =>
            ApiEndpointRule::where('endpoint_key', $ruleKey)->first()
        );

        if ($rule) {
            if (! $rule->is_enabled) {
                return response()->json([
                    'message' => 'Endpoint ini sedang dinonaktifkan oleh Admin API Management.',
                    'rule'    => $ruleKey,
                ], 503);
            }

            $key = "rule:{$ruleKey}:" . ($request->user()?->id ?: $request->ip());
            if (RateLimiter::tooManyAttempts($key, max(1, $rule->rate_limit_per_min))) {
                return response()->json([
                    'message'     => "Batas laju ({$rule->rate_limit_per_min}/menit) untuk {$ruleKey} tercapai.",
                    'retry_after' => RateLimiter::availableIn($key),
                ], 429);
            }
            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }
}
