<?php
/**
 * Phase 7: Enhanced App Configuration with SEO defaults.
 */

return [
    'name'      => env('APP_NAME', 'Pemutar Musik Interaktif'),
    'env'       => env('APP_ENV', 'production'),
    'debug'     => (bool) env('APP_DEBUG', false),
    'url'       => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),
    'timezone'  => 'Asia/Jakarta',
    'locale'    => 'id',
    'fallback_locale' => 'en',
    'faker_locale'    => 'id_ID',
    'cipher'          => 'AES-256-CBC',
    'key'             => env('APP_KEY'),
    'previous_keys'   => [],
    'maintenance'     => ['driver' => 'file'],

    // Phase 7: Default SEO meta
    'seo' => [
        'title_suffix' => ' — Musikku',
        'default_description' => 'Pemutar musik interaktif berbasis web. Dengarkan, buat playlist, dan temukan lagu favorit Anda.',
        'default_og_image'    => '/images/og-default.png',
    ],
];
