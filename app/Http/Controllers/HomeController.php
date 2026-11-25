<?php
namespace App\Http\Controllers;

use App\Models\{Album, Artist, Genre, LrsStatement, PlayHistory, Playlist, Song};
use App\Services\LrsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {
        $with = ['artist', 'album'];

        $recommended = collect();
        $userStats   = null;

        if ($user = auth()->user()) {
            $genreIds = PlayHistory::where('play_histories.user_id', $user->id)
                ->join('songs', 'songs.id', '=', 'play_histories.song_id')
                ->whereNotNull('songs.genre_id')
                ->selectRaw('songs.genre_id, count(*) as c')
                ->groupBy('songs.genre_id')->orderByDesc('c')->limit(3)->pluck('genre_id');

            if ($genreIds->isNotEmpty()) {
                $recommended = Song::with($with)->whereIn('genre_id', $genreIds)->inRandomOrder()->limit(8)->get();
            }

            $userStats = [
                'favorites' => $user->favoriteSongs()->count(),
                'playlists' => $user->playlists()->count(),
                'histories' => $user->playHistories()->count(),
            ];
        }

        $platformStats = Cache::remember('home:stats', 120, fn () => [
            'songs'     => Song::count(),
            'artists'   => Artist::count(),
            'albums'    => Album::count(),
            'plays'     => (int) Song::sum('play_count'),
        ]);

        $publicPlaylists = Playlist::with('user:id,name')
            ->withCount('songs')
            ->where('is_public', true)
            ->latest()->limit(6)->get();

        $recentPlays = PlayHistory::with(['song.artist:id,name', 'user:id,name'])
            ->whereHas('song')
            ->latest('played_at')
            ->limit(6)->get();

        return view('home', [
            'trending'        => Song::with($with)->orderByDesc('play_count')->limit(8)->get(),
            'newest'          => Song::with($with)->latest()->limit(8)->get(),
            'albums'          => Album::with('artist')->latest()->limit(6)->get(),
            'artists'         => Artist::withCount('songs')->orderByDesc('songs_count')->limit(6)->get(),
            'genres'          => Genre::withCount('songs')->orderBy('name')->get(),
            'recommended'     => $recommended,
            'platformStats'   => $platformStats,
            'publicPlaylists' => $publicPlaylists,
            'recentPlays'     => $recentPlays,
            'userStats'       => $userStats,
        ]);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $songs = $artists = $albums = collect();

        if ($q !== '') {
            $songs   = Song::with(['artist', 'album'])->search($q)->orderByDesc('play_count')->limit(30)->get();
            $artists = Artist::where('name', 'like', "%{$q}%")->limit(8)->get();
            $albums  = Album::with('artist')->where('title', 'like', "%{$q}%")->limit(8)->get();
            LrsService::record('searched', 'Search', null, $q, ['results' => $songs->count()]);
        }
        return view('search', compact('q', 'songs', 'artists', 'albums'));
    }

    public function charts()
    {
        return view('charts', [
            'week' => Song::with(['artist', 'album'])
                ->withCount(['histories as week_plays' => fn ($q) => $q->where('played_at', '>=', now()->subDays(7))])
                ->orderByDesc('week_plays')->orderByDesc('play_count')->limit(20)->get(),
            'all'  => Song::with(['artist', 'album'])->orderByDesc('play_count')->limit(20)->get(),
        ]);
    }
}
