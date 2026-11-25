<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Album, Artist, PlayHistory, Playlist, Song, User};

class DashboardController extends Controller
{
    public function index()
    {
        $perDay = PlayHistory::where('played_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('date(played_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');

        $days = collect(range(6, 0))->map(function ($i) use ($perDay) {
            $date = now()->subDays($i)->toDateString();
            return ['label' => now()->subDays($i)->format('d/m'), 'count' => (int) ($perDay[$date] ?? 0)];
        });

        return view('admin.dashboard', [
            'stats' => [
                'Pengguna' => User::count(), 'Lagu' => Song::count(), 'Artis' => Artist::count(),
                'Album' => Album::count(), 'Playlist' => Playlist::count(), 'Total putar' => (int) Song::sum('play_count'),
            ],
            'days' => $days,
            'max'  => max(1, $days->max('count')),
            'top'  => Song::with('artist')->orderByDesc('play_count')->limit(5)->get(),
        ]);
    }
}
