<?php
/**
 * Phase 6: Enhanced Cache Configuration
 * Supports file (default), database, Redis, and array drivers.
 */

return [
    'default' => env('CACHE_STORE', 'file'),

    'stores' => [
        'array' => [
            'driver'    => 'array',
            'serialize' => false,
        ],

        'file' => [
            'driver'    => 'file',
            'path'      => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'database' => [
            'driver'     => 'database',
            'connection' => null,
            'table'      => env('CACHE_DATABASE_TABLE', 'cache'),
            'lock_connection' => null,
            'lock_table' => null,
        ],

        'redis' => [
            'driver'     => 'redis',
            'connection' => env('CACHE_REDIS_CONNECTION', 'cache'),
            'lock_connection' => env('CACHE_REDIS_LOCK_CONNECTION', 'default'),
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'musik_cache_'),
];
