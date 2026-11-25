<?php

namespace App\Http\Controllers\Admin;

use App\Events\SecurityAlertEvent;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, BackupLog, ErrorLog, LrsStatement, SeoMetadata, User};
use App\Services\ErrorTrackerService;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, Cache, DB};

class OperationsController extends Controller
{
    /**
     * Phase 9: Error Tracking & Real-time Logs Alert (Admin ONLY)
     */
    public function logs(Request $request)
    {
        $logs = ErrorLog::with(['user:id,name', 'resolver:id,name'])
            ->when($request->query('level'), fn ($q, $l) => $q->where('level', $l))
            ->when($request->query('status') === 'open', fn ($q) => $q->where('is_resolved', false))
            ->when($request->query('status') === 'resolved', fn ($q) => $q->where('is_resolved', true))
            ->latest()
            ->paginate(20)->withQueryString();

        $summary = [
            'unresolved' => ErrorLog::unresolved()->count(),
            'last_24h'   => ErrorLog::recent(24)->count(),
            'critical'   => ErrorLog::unresolved()->whereIn('level', ['emergency', 'alert', 'critical', 'error'])->count(),
            'total'      => ErrorLog::count(),
        ];

        return view('admin.logs', compact('logs', 'summary'));
    }

    public function resolveLog(ErrorLog $errorLog)
    {
        $errorLog->markResolved();
        AuditLog::record('resolve_error', 'ErrorLog', $errorLog->id);
        return back()->with('ok', "Error #{$errorLog->id} ditandai selesai.");
    }

    public function resolveAllLogs()
    {
        $count = ErrorLog::unresolved()->update([
            'is_resolved' => true,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);
        AuditLog::record('resolve_all_errors', 'ErrorLog', null, description: "Resolved {$count} error logs");
        return back()->with('ok', "{$count} log error ditandai selesai.");
    }

    public function deleteLog(ErrorLog $errorLog)
    {
        $errorLog->delete();
        return back()->with('ok', 'Log error dihapus.');
    }

    public function testAlert()
    {
        $log = ErrorTrackerService::logManual(
            'critical',
            'admin-test',
            'Simulasi Error Alert Real-time (Reverb/Pusher) oleh Admin ' . auth()->user()->name,
            ['triggered_by' => auth()->id(), 'node' => env('APP_NODE_ID', 'node-1')]
        );

        return back()->with('ok', "Alert uji (#{$log?->id}) dicatat & disiarkan ke channel private-admin.alerts.");
    }

    /**
     * Phase 4: Security, Audit Logs & LRS Local
     */
    public function security(Request $request)
    {
        $audits = AuditLog::with('user:id,name,email')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->latest('created_at')
            ->paginate(15, ['*'], 'audit_page')->withQueryString();

        $lrsStatements = LrsStatement::with('user:id,name')
            ->when($request->query('verb'), fn ($q, $v) => $q->where('verb', $v))
            ->latest('stored_at')
            ->paginate(15, ['*'], 'lrs_page')->withQueryString();

        $lockedUsers = User::whereNotNull('locked_until')
            ->where('locked_until', '>', now())
            ->orWhere('is_active', false)
            ->get();

        $lrsFile = storage_path('app/lrs/statements.jsonl');
        $lrsStats = [
            'total_statements' => LrsStatement::count(),
            'total_audits'     => AuditLog::count(),
            'locked_accounts'  => $lockedUsers->count(),
            'jsonl_size'       => file_exists($lrsFile) ? round(filesize($lrsFile) / 1024, 2) . ' KB' : '0 KB',
        ];

        return view('admin.security', compact('audits', 'lrsStatements', 'lockedUsers', 'lrsStats'));
    }

    public function exportLrs()
    {
        $file = storage_path('app/lrs/statements.jsonl');
        if (! file_exists($file)) {
            $rows = LrsStatement::orderBy('id')->get()->map(fn ($s) => json_encode([
                'id'        => $s->statement_id,
                'actor'     => $s->actor_name,
                'verb'      => $s->verb,
                'object'    => ['type' => $s->object_type, 'id' => $s->object_id, 'name' => $s->object_name],
                'signature' => $s->signature,
                'stored_at' => $s->stored_at?->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE))->implode(PHP_EOL);

            return response($rows, 200, [
                'Content-Type'        => 'application/x-ndjson',
                'Content-Disposition' => 'attachment; filename="lrs_statements_' . date('Ymd') . '.jsonl"',
            ]);
        }

        return response()->download($file, 'lrs_statements_' . date('Ymd') . '.jsonl');
    }

    /**
     * Phase 2, 6, 7, 8: Database, Storage, Cache, Load Balancing, SEO & Recovery
     */
    public function system()
    {
        $dbOk = true;
        $dbVersion = '';
        try {
            DB::select('SELECT 1');
            $dbVersion = DB::selectOne('SELECT VERSION() as v')->v ?? 'MySQL';
        } catch (\Throwable) {
            $dbOk = false;
        }

        $storagePath = storage_path('app');
        $health = [
            'db_ok'        => $dbOk,
            'db_version'   => $dbVersion,
            'db_host'      => config('database.connections.mysql.host'),
            'db_read_host' => config('database.connections.mysql_read.host'),
            'node_id'      => env('APP_NODE_ID', gethostname() ?: 'node-1'),
            'cache_driver' => config('cache.default'),
            'broadcast'    => config('broadcasting.default'),
            'cdn_enabled'  => config('cdn.enabled'),
            'cdn_url'      => config('cdn.url') ?: '(local)',
            'hash_driver'  => config('hashing.driver') . ' (rounds: ' . config('hashing.bcrypt.rounds') . ')',
            'free_disk_gb' => round(disk_free_space($storagePath) / 1073741824, 2),
            'php_version'  => PHP_VERSION,
            'laravel'      => app()->version(),
        ];

        $backups = BackupLog::with('initiator:id,name')->latest()->limit(15)->get();
        $seos    = SeoMetadata::orderBy('route_name')->get();

        return view('admin.system', compact('health', 'backups', 'seos'));
    }

    public function createBackup()
    {
        $db       = config('database.connections.mysql.database');
        $user     = config('database.connections.mysql.username');
        $pass     = config('database.connections.mysql.password');
        $host     = config('database.connections.mysql.host');
        $port     = config('database.connections.mysql.port', 3306);
        $filename = "backup_{$db}_" . now()->format('Ymd_His') . '.sql';
        $path     = storage_path("app/backups/{$filename}");

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $log = BackupLog::create([
            'filename'     => $filename,
            'disk'         => 'backups',
            'status'       => 'in_progress',
            'initiated_by' => auth()->id(),
        ]);

        // Locate mysqldump (Windows Laragon or PATH)
        $mysqldump = 'mysqldump';
        foreach ([
            'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        ] as $candidate) {
            if (file_exists($candidate)) {
                $mysqldump = '"' . $candidate . '"';
                break;
            }
        }

        $cmd = sprintf(
            '%s --host=%s --port=%s --user=%s %s %s > %s 2>&1',
            $mysqldump,
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($user),
            $pass ? '--password=' . escapeshellarg($pass) : '',
            escapeshellarg($db),
            escapeshellarg($path),
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($path) || filesize($path) === 0) {
            // Pure-PHP SQL snapshot fallback so recovery ALWAYS succeeds even if mysqldump binary isn't in PATH
            $sql = $this->phpSqlSnapshot($db);
            file_put_contents($path, $sql);
        }

        $log->update([
            'status'     => 'completed',
            'size_bytes' => filesize($path),
            'notes'      => $exitCode === 0 ? 'mysqldump' : 'php-snapshot-fallback',
        ]);

        AuditLog::record('backup', 'BackupLog', $log->id, description: "Backup created: {$filename}");
        SafeBroadcast::dispatch(new SecurityAlertEvent('backup_completed', "Backup database {$filename} selesai.", request()->ip()));

        return back()->with('ok', "Backup {$filename} berhasil dibuat (" . $log->size_human . ').');
    }

    public function downloadBackup(BackupLog $backupLog)
    {
        $path = storage_path('app/backups/' . basename($backupLog->filename));
        abort_unless(file_exists($path), 404, 'File backup tidak ditemukan.');
        return response()->download($path);
    }

    public function clearCache()
    {
        Cache::flush();
        Artisan::call('view:clear');
        AuditLog::record('cache_clear', null, null, description: 'Cleared application & view cache');
        return back()->with('ok', 'Cache aplikasi & view berhasil dibersihkan.');
    }

    public function saveSeo(Request $request)
    {
        $data = $request->validate([
            'route_name'  => 'required|string|max:100',
            'title'       => 'nullable|string|max:160',
            'description' => 'nullable|string|max:320',
            'og_image'    => 'nullable|string|max:255',
        ]);

        SeoMetadata::updateOrCreate(['route_name' => $data['route_name']], $data);
        Cache::forget("seo:route:{$data['route_name']}");
        Cache::forget('seo:sitemap:xml');

        AuditLog::record('seo_update', 'SeoMetadata', null, newValues: $data);
        return back()->with('ok', "Metadata SEO untuk '{$data['route_name']}' disimpan.");
    }

    public function deleteSeo(SeoMetadata $seoMetadata)
    {
        Cache::forget("seo:route:{$seoMetadata->route_name}");
        $seoMetadata->delete();
        return back()->with('ok', 'Metadata SEO dihapus.');
    }

    private function phpSqlSnapshot(string $db): string
    {
        $out = "-- Musikku PHP SQL Backup ({$db}) at " . now()->toIso8601String() . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach (DB::select('SHOW TABLES') as $row) {
            $table = array_values((array) $row)[0];
            $create = DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $ddl = array_values((array) $create)[1] ?? '';
            $out .= "DROP TABLE IF EXISTS `{$table}`;\n{$ddl};\n\n";

            foreach (DB::table($table)->get() as $record) {
                $arr = (array) $record;
                $cols = implode('`,`', array_keys($arr));
                $vals = implode(',', array_map(fn ($v) => is_null($v) ? 'NULL' : DB::getPdo()->quote((string) $v), array_values($arr)));
                $out .= "INSERT INTO `{$table}` (`{$cols}`) VALUES ({$vals});\n";
            }
            $out .= "\n";
        }
        return $out . "SET FOREIGN_KEY_CHECKS=1;\n";
    }
}
