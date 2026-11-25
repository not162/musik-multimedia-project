<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Album, Artist, PlayHistory, Playlist, Song, User};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Cache, DB};

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('admin:dashboard:stats', 60, function () {
            $perDay = PlayHistory::where('played_at', '>=', now()->subDays(6)->startOfDay())
                ->selectRaw('date(played_at) as d, count(*) as c')
                ->groupBy('d')->pluck('c', 'd');

            $days = collect(range(6, 0))->map(function ($i) use ($perDay) {
                $date = now()->subDays($i)->toDateString();
                return ['label' => now()->subDays($i)->format('d/m'), 'count' => (int) ($perDay[$date] ?? 0)];
            });

            return [
                'counts' => [
                    'users'      => User::count(),
                    'songs'      => Song::count(),
                    'artists'    => Artist::count(),
                    'albums'     => Album::count(),
                    'playlists'  => Playlist::count(),
                    'total_plays' => (int) Song::sum('play_count'),
                ],
                'plays_per_day' => $days,
                'top_songs'     => Song::with('artist:id,name')->orderByDesc('play_count')->limit(10)->get(),
            ];
        });

        return response()->json($stats);
    }

    /**
     * Phase 8: Health check endpoint
     */
    public function health(): JsonResponse
    {
        $checks = [];

        // Database
        try {
            DB::select('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Storage
        $storagePath = storage_path('app');
        $checks['storage'] = [
            'status'    => is_writable($storagePath) ? 'ok' : 'error',
            'free_disk' => round(disk_free_space($storagePath) / 1073741824, 2) . ' GB',
        ];

        // Cache
        try {
            Cache::put('health_check', true, 10);
            $checks['cache'] = ['status' => Cache::get('health_check') ? 'ok' : 'error'];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $allOk = collect($checks)->every(fn ($c) => $c['status'] === 'ok');

        return response()->json([
            'status'  => $allOk ? 'healthy' : 'degraded',
            'checks'  => $checks,
            'php'     => PHP_VERSION,
            'laravel' => app()->version(),
            'time'    => now()->toIso8601String(),
        ], $allOk ? 200 : 503);
    }
}
