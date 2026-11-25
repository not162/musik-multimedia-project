<?php
namespace App\Http\Controllers;

use App\Events\SongPlayedEvent;
use App\Models\{Genre, Song};
use App\Services\LrsService;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SongController extends Controller
{
    public function index(Request $request)
    {
        $sort = $request->query('sort', 'terbaru');

        $songs = Song::with(['artist', 'album'])
            ->search($request->query('q'))
            ->when($request->query('genre'), fn ($q, $g) => $q->where('genre_id', $g))
            ->when($sort === 'populer', fn ($q) => $q->orderByDesc('play_count'), fn ($q) => $q)
            ->when($sort === 'judul', fn ($q) => $q->orderBy('title'))
            ->when($sort === 'terbaru', fn ($q) => $q->latest())
            ->paginate(20)->withQueryString();

        return view('songs.index', ['songs' => $songs, 'genres' => Genre::orderBy('name')->get(), 'sort' => $sort]);
    }

    public function show(Song $song)
    {
        $song->load(['artist', 'album', 'genre']);

        $related = Song::with(['artist', 'album'])->where('id', '!=', $song->id)
            ->where(fn ($q) => $q->where('artist_id', $song->artist_id)
                ->when($song->genre_id, fn ($w) => $w->orWhere('genre_id', $song->genre_id)))
            ->inRandomOrder()->limit(8)->get();

        return view('songs.show', compact('song', 'related'));
    }

    // Stream audio; response()->file() mendukung HTTP Range sehingga seek/tua-maju berfungsi
    public function stream(Song $song)
    {
        abort_unless(Storage::disk('public')->exists($song->file_path), 404);
        return response()->file(Storage::disk('public')->path($song->file_path));
    }

    // Dipanggil JS setelah lagu diputar beberapa detik (hindari hitungan palsu)
    public function played(Song $song)
    {
        $song->increment('play_count');
        $song->histories()->create(['user_id' => auth()->id(), 'played_at' => now()]);

        LrsService::record('played', 'Song', $song->id, $song->title, [
            'artist' => $song->artist?->name,
        ]);

        SafeBroadcast::dispatch(new SongPlayedEvent($song->fresh(), auth()->user()?->name));

        return response()->noContent();
    }
}
