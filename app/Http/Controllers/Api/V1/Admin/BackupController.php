<?php
/**
 * Phase 8: Backup Controller — Admin only
 */

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, BackupLog};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Artisan, Process};

class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => BackupLog::with('initiator:id,name')->latest()->paginate(20),
        ]);
    }

    /**
     * Trigger a MySQL backup (mysqldump).
     */
    public function store(Request $request): JsonResponse
    {
        $db       = config('database.connections.mysql.database');
        $user     = config('database.connections.mysql.username');
        $pass     = config('database.connections.mysql.password');
        $host     = config('database.connections.mysql.host');
        $filename = "backup_{$db}_" . now()->format('Ymd_His') . '.sql';
        $path     = storage_path("app/backups/{$filename}");

        // Ensure directory exists
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $log = BackupLog::create([
            'filename'     => $filename,
            'disk'         => 'local',
            'status'       => 'in_progress',
            'initiated_by' => auth()->id(),
        ]);

        try {
            $cmd = sprintf(
                'mysqldump --host=%s --user=%s %s %s > %s',
                escapeshellarg($host),
                escapeshellarg($user),
                $pass ? '--password=' . escapeshellarg($pass) : '',
                escapeshellarg($db),
                escapeshellarg($path),
            );

            exec($cmd . ' 2>&1', $output, $exitCode);

            if ($exitCode !== 0) {
                $log->update(['status' => 'failed', 'notes' => implode("\n", $output)]);
                return response()->json(['message' => 'Backup gagal.', 'error' => implode("\n", $output)], 500);
            }

            $log->update([
                'status'     => 'completed',
                'size_bytes' => filesize($path),
            ]);

            AuditLog::record('backup', null, null, description: "Database backup: {$filename}");

            return response()->json([
                'message' => 'Backup berhasil.',
                'backup'  => $log->fresh(),
            ], 201);

        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'notes' => $e->getMessage()]);
            return response()->json(['message' => 'Backup error.', 'error' => $e->getMessage()], 500);
        }
    }
}
