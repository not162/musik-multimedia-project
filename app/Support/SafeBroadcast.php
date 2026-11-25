<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class SafeBroadcast
{
    public static function dispatch(object $event): void
    {
        try {
            if (config('broadcasting.default') === 'null') {
                return;
            }
            event($event);
        } catch (\Throwable $e) {
            Log::warning('Broadcast skipped (server offline?): ' . $e->getMessage());
        }
    }
}
