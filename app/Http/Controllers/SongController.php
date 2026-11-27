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

        $songs = Song::with(['artist', 'album', 'genre', 'recordLabel'])
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
        $song->load(['artist', 'album', 'genre', 'recordLabel']);

        $related = Song::with(['artist', 'album', 'genre', 'recordLabel'])->where('id', '!=', $song->id)
            ->where(fn ($q) => $q->where('artist_id', $song->artist_id)
                ->when($song->genre_id, fn ($w) => $w->orWhere('genre_id', $song->genre_id)))
            ->inRandomOrder()->limit(8)->get();

        return view('songs.show', compact('song', 'related'));
    }

    // Stream audio / video MP4; response()->file() mendukung HTTP Range sehingga seek bekerja lancar
    public function stream(Song $song)
    {
        abort_unless(Storage::disk('public')->exists($song->file_path), 404);
        $path = Storage::disk('public')->path($song->file_path);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'mp4'  => 'video/mp4',
            'm4a', 'aac' => 'audio/mp4',
            'wav'  => 'audio/wav',
            'ogg'  => 'audio/ogg',
            'flac' => 'audio/flac',
            default => 'audio/mpeg',
        };
        return response()->file($path, [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',
            'Content-Type'  => $mime,
        ]);
    }

    // Unduh arsip master resolusi tinggi 100 kHz - 1.100 kHz (jika tersedia)
    public function downloadHires(Song $song)
    {
        abort_unless($song->hires_file_path && Storage::disk('public')->exists($song->hires_file_path), 404, 'Berkas master resolusi tinggi belum tersedia untuk lagu ini.');
        $path = Storage::disk('public')->path($song->hires_file_path);
        $name = str($song->title)->slug() . '-' . ($song->target_sample_rate_khz ?: 'hires') . 'khz.wav';
        return response()->download($path, $name, ['Content-Type' => 'audio/wav']);
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
