<?php
/**
 * Phase 6: CDN & Static Caching Configuration
 */

return [
    'enabled' => (bool) env('CDN_ENABLED', false),
    'url'     => rtrim((string) env('CDN_URL', env('ASSET_URL', '')), '/'),
    'ttl'     => [
        'public_catalog' => (int) env('CACHE_TTL_CATALOG', 300),
        'charts'         => (int) env('CACHE_TTL_CHARTS', 180),
        'static_assets'  => 31536000,
    ],
];
