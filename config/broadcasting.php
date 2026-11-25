<?php
/**
 * Broadcasting Configuration: Laravel Reverb (local WebSocket) + Pusher Channels.
 * Switch driver via BROADCAST_CONNECTION=reverb or BROADCAST_CONNECTION=pusher.
 */

return [
    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    'connections' => [
        'reverb' => [
            'driver'  => 'reverb',
            'key'     => env('REVERB_APP_KEY', 'musikku-local-key'),
            'secret'  => env('REVERB_APP_SECRET', 'musikku-local-secret'),
            'app_id'  => env('REVERB_APP_ID', 'musikku-app'),
            'options' => [
                'host'   => env('REVERB_HOST', '127.0.0.1'),
                'port'   => (int) env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [],
        ],

        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY', ''),
            'secret'  => env('PUSHER_APP_SECRET', ''),
            'app_id'  => env('PUSHER_APP_ID', ''),
            'options' => [
                'cluster'   => env('PUSHER_APP_CLUSTER', 'ap1'),
                'host'      => env('PUSHER_HOST') ?: 'api-' . env('PUSHER_APP_CLUSTER', 'ap1') . '.pusher.com',
                'port'      => (int) env('PUSHER_PORT', 443),
                'scheme'    => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS'    => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
