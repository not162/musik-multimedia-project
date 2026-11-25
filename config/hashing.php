<?php
/**
 * Phase 3: Password Hashing & Salting Configuration.
 * Uses bcrypt (per-password cryptographic 128-bit random salt, cost=12)
 * or argon2id when selected via HASH_DRIVER.
 */

return [
    'driver' => env('HASH_DRIVER', 'bcrypt'),

    'bcrypt' => [
        'rounds' => (int) env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit'  => null,
    ],

    'argon' => [
        'memory'  => 65536,
        'threads' => 1,
        'time'    => 4,
        'verify'  => true,
    ],

    'rehash_on_login' => true,
];
