<?php
/**
 * Phase 9: Error Tracking & Real-time Admin Alert Service
 */

namespace App\Services;

use App\Events\ErrorAlertOccurred;
use App\Models\ErrorLog;

class ErrorTrackerService
{
    public static function capture(\Throwable $e, string $level = 'error', string $channel = 'exception'): ?ErrorLog
    {
        try {
            if (! app()->bound('db')) {
                return null;
            }

            $log = ErrorLog::create([
                'level'       => $level,
                'channel'     => $channel,
                'message'     => substr($e->getMessage() ?: get_class($e), 0, 500),
                'context'     => json_encode([
                    'class' => get_class($e),
                    'code'  => $e->getCode(),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                ]),
                'stack_trace' => substr($e->getTraceAsString(), 0, 10000),
                'url'         => request()?->fullUrl(),
                'method'      => request()?->method(),
                'ip_address'  => request()?->ip(),
                'user_id'     => auth()->id(),
            ]);

            self::dispatchAlert($log);

            return $log;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function logManual(string $level, string $channel, string $message, array $context = []): ?ErrorLog
    {
        try {
            $log = ErrorLog::create([
                'level'      => $level,
                'channel'    => $channel,
                'message'    => substr($message, 0, 500),
                'context'    => $context ? json_encode($context) : null,
                'url'        => request()?->fullUrl(),
                'method'     => request()?->method(),
                'ip_address' => request()?->ip(),
                'user_id'    => auth()->id(),
            ]);

            self::dispatchAlert($log);

            return $log;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function dispatchAlert(ErrorLog $log): void
    {
        try {
            // Never let broadcast failure break error capture (e.g., if Reverb/Pusher server isn't running)
            if (config('broadcasting.default') !== 'null') {
                event(new ErrorAlertOccurred($log));
            }
        } catch (\Throwable) {
            // Ignore broadcast transport error when WS server is offline
        }
    }
}
