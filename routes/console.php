<?php
/**
 * Artisan Console Commands & Scheduled Tasks
 *
 * Phase 8: Health check & scheduled maintenance
 * Phase 9: Error log cleanup command
 */

use Illuminate\Support\Facades\{Artisan, Schedule};

// Phase 9: Cleanup old resolved error logs (older than 30 days)
Artisan::command('logs:cleanup {--days=30}', function () {
    $days = (int) $this->option('days');
    $deleted = \App\Models\ErrorLog::where('is_resolved', true)
        ->where('resolved_at', '<', now()->subDays($days))
        ->delete();

    $this->info("Deleted {$deleted} resolved error logs older than {$days} days.");
})->purpose('Clean up old resolved error logs');

// Phase 8: Cleanup old audit logs
Artisan::command('audit:cleanup {--days=90}', function () {
    $days = (int) $this->option('days');
    $deleted = \App\Models\AuditLog::where('created_at', '<', now()->subDays($days))->delete();

    $this->info("Deleted {$deleted} audit logs older than {$days} days.");
})->purpose('Clean up old audit logs');

// Phase 6: Clear API cache
Artisan::command('cache:api-clear', function () {
    \Illuminate\Support\Facades\Cache::flush();
    $this->info('API cache cleared.');
})->purpose('Clear all API response caches');

// Phase 8: Health check command
Artisan::command('health:check', function () {
    try {
        \Illuminate\Support\Facades\DB::select('SELECT 1');
        $this->info('✓ Database: OK');
    } catch (\Throwable $e) {
        $this->error('✗ Database: ' . $e->getMessage());
    }

    $storagePath = storage_path('app');
    $this->info(is_writable($storagePath)
        ? '✓ Storage: Writable'
        : '✗ Storage: Not writable');

    $this->info('✓ PHP: ' . PHP_VERSION);
    $this->info('✓ Laravel: ' . app()->version());
    $this->info('✓ Disk free: ' . round(disk_free_space($storagePath) / 1073741824, 2) . ' GB');
})->purpose('Run application health check');

// Scheduled maintenance tasks
Schedule::command('logs:cleanup')->daily();
Schedule::command('audit:cleanup')->weekly();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
