<?php
/**
 * Phase 6: ETag-based HTTP caching for GET responses.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        /** @var Response $response */
        $response = $next($request);

        // Only cache successful responses
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        // Generate ETag from content
        $content = $response->getContent();
        if ($content === false) {
            return $response;
        }

        $etag = '"' . md5($content) . '"';
        $response->headers->set('ETag', $etag);

        // Check If-None-Match
        if ($request->header('If-None-Match') === $etag) {
            $response->setStatusCode(304);
            $response->setContent('');
        }

        // Public cache for anonymous, private for authenticated
        $isPublic = ! $request->user();
        $maxAge   = $isPublic ? 300 : 60; // 5 min public, 1 min private
        $response->headers->set('Cache-Control', ($isPublic ? 'public' : 'private') . ", max-age={$maxAge}");

        return $response;
    }
}
