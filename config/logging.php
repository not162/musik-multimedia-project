<?php
/**
 * Phase 9: Enhanced Logging Configuration
 * Multiple channels: single file, daily rotation, API-specific, database errors.
 */

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => 'single',
        'trace'   => false,
    ],

    'channels' => [
        'stack' => [
            'driver'   => 'stack',
            'channels' => explode(',', env('LOG_STACK', 'single,daily')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        // Phase 5: Dedicated API log channel
        'api' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/api.log'),
            'level'  => 'info',
            'days'   => 30,
            'replace_placeholders' => true,
        ],

        // Phase 4: Security / audit events
        'security' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/security.log'),
            'level'  => 'info',
            'days'   => 90,
            'replace_placeholders' => true,
        ],

        'emergency' => [
            'driver' => 'single',
            'path'   => storage_path('logs/emergency.log'),
        ],
    ],
];
