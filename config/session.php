<?php
/**
 * Phase 4 & 6: Enhanced Session Configuration
 * Hardened cookie settings with encryption.
 */

use Illuminate\Support\Str;

return [
    'driver'          => env('SESSION_DRIVER', 'file'),
    'lifetime'        => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt'         => true,
    'files'           => storage_path('framework/sessions'),
    'connection'      => env('SESSION_CONNECTION'),
    'table'           => 'sessions',
    'store'           => env('SESSION_STORE'),
    'lottery'         => [2, 100],
    'cookie'          => Str::slug(env('APP_NAME', 'laravel'), '_').'_session',
    'path'            => '/',
    'domain'          => env('SESSION_DOMAIN'),
    'secure'          => env('SESSION_SECURE_COOKIE', str_starts_with((string) env('APP_URL'), 'https://')),
    'http_only'       => true,
    'same_site'       => 'lax',
    'partitioned'     => false,
];
