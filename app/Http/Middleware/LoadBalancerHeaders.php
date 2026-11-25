<?php
/**
 * Phase 7: Load Balancing & Node Identification Headers
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadBalancerHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Node-Id', (string) env('APP_NODE_ID', gethostname() ?: 'node-1'));
        $response->headers->set('X-Cache-Store', (string) config('cache.default'));

        return $response;
    }
}
