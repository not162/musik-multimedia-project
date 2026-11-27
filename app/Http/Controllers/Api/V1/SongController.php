<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\SongPlayedEvent;
use App\Http\Controllers\Controller;
use App\Models\{RecordLabel, Song};
use App\Services\{CatalogSyncService, LrsService};
use App\Support\SafeBroadcast;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Cache;

class SongController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ver      = CatalogSyncService::version();
        $cacheKey = "api:v{$ver}:songs:" . md5($request->fullUrl());

        $songs = Cache::remember($cacheKey, 120, function () use ($request) {
            return Song::with(['artist:id,name', 'album:id,title,cover', 'genre:id,name', 'recordLabel:id,name,slug'])
                ->search($request->query('q'))
                ->when($request->query('genre'), fn ($q, $g) => $q->where('genre_id', $g))
                ->when($request->query('artist'), fn ($q, $a) => $q->where('artist_id', $a))
                ->when($request->query('label'), fn ($q, $l) => $q->where('record_label_id', $l))
                ->when($request->boolean('finetuned'), fn ($q) => $q->where('is_ai_finetuned', true))
                ->when($request->query('sort') === 'popular', fn ($q) => $q->orderByDesc('play_count'))
                ->when($request->query('sort') === 'title', fn ($q) => $q->orderBy('title'))
                ->when(! in_array($request->query('sort'), ['popular', 'title']), fn ($q) => $q->latest())
                ->paginate(min((int) $request->query('per_page', 20), 100))
                ->withQueryString();
        });

        return response()->json($songs);
    }

    public function show(Song $song): JsonResponse
    {
        $song->load(['artist', 'album', 'genre', 'recordLabel']);

        $related = Cache::remember("api:song:{$song->id}:related", 300, function () use ($song) {
            return Song::with(['artist:id,name', 'recordLabel:id,name,slug'])
                ->where('id', '!=', $song->id)
                ->where(fn ($q) => $q->where('artist_id', $song->artist_id)
                    ->when($song->genre_id, fn ($w) => $w->orWhere('genre_id', $song->genre_id)))
                ->inRandomOrder()->limit(8)->get();
        });

        return response()->json([
            'song'    => $song,
            'related' => $related,
        ]);
    }

    /**
     * Connected endpoint: returns Admin's AI fine-tuning DSP profile for this song
     * so User player & Public preview can apply it seamlessly in real-time.
     */
    public function aiProfile(Song $song): JsonResponse
    {
        $song->loadMissing(['artist:id,name', 'recordLabel:id,name,slug']);

        return response()->json([
            'song_id'               => $song->id,
            'title'                 => $song->title,
            'media_format'          => $song->media_format ?: pathinfo((string) $song->file_path, PATHINFO_EXTENSION),
            'target_sample_rate_khz'=> $song->target_sample_rate_khz,
            'has_hires_master'      => ! empty($song->hires_file_path),
            'hires_url'             => $song->hires_file_path ? route('songs.hires', $song) : null,
            'stream_url'            => route('songs.stream', $song),
            'label'                 => $song->recordLabel,
            'is_ai_finetuned'       => (bool) $song->is_ai_finetuned,
            'is_voice_smoothed'     => (bool) $song->is_voice_smoothed,
            'voice_preset'          => $song->voice_preset,
            'clarity_score'         => $song->voice_clarity_score,
            'smoothness_score'      => $song->voice_smoothness_score,
            'mood'                  => $song->ai_mood,
            'bpm'                   => $song->ai_tempo_bpm,
            'direction'             => $song->ai_direction,
            'eq_bands'              => $song->ai_eq_bands ?? [0,0,0,0,0,0,0,0,0,0],
            'preamp_db'             => (float) $song->ai_preamp_db,
            'pitch_rate'            => (float) ($song->ai_pitch_rate ?: 1.0),
            'mastering_notes'       => $song->ai_mastering_notes,
            'audio_meta'            => $song->audio_meta,
            'finetuned_at'          => $song->ai_finetuned_at?->toIso8601String(),
        ]);
    }

    /**
     * Endpoint sinkronisasi otomatis untuk views Guest Public, User & Admin.
     */
    public function liveSync(): JsonResponse
    {
        $latestSongs = Song::with(['artist:id,name', 'recordLabel:id,name,slug'])
            ->latest()->limit(6)->get()
            ->map(fn (Song $s) => [
                'id'           => $s->id,
                'title'        => $s->title,
                'artist'       => $s->artist?->name,
                'label'        => $s->recordLabel?->name,
                'media_format' => $s->media_format ?: 'mp3',
                'freq_khz'     => $s->target_sample_rate_khz,
                'smoothed'     => (bool) $s->is_voice_smoothed,
                'url'          => route('songs.show', $s),
            ]);

        return response()->json([
            'catalog_version' => CatalogSyncService::version(),
            'last_event'      => CatalogSyncService::lastEvent(),
            'latest_songs'    => $latestSongs,
            'labels_count'    => RecordLabel::count(),
            'songs_count'     => Song::count(),
        ]);
    }

    public function stream(Song $song)
    {
        $path = storage_path('app/public/' . $song->file_path);
        abort_unless(file_exists($path), 404, 'File not found.');

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'mp4'        => 'video/mp4',
            'm4a', 'aac' => 'audio/mp4',
            'wav'        => 'audio/wav',
            'ogg'        => 'audio/ogg',
            'flac'       => 'audio/flac',
            default      => 'audio/mpeg',
        };

        return response()->file($path, [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',
            'Content-Type'  => $mime,
        ]);
    }

    public function played(Song $song): JsonResponse
    {
        $song->increment('play_count');
        $song->histories()->create(['user_id' => auth('sanctum')->id(), 'played_at' => now()]);

        LrsService::record('played', 'Song', $song->id, $song->title, ['channel' => 'api']);
        SafeBroadcast::dispatch(new SongPlayedEvent($song->fresh(), auth('sanctum')->user()?->name));

        Cache::forget("api:song:{$song->id}:related");

        return response()->json(['message' => 'Play recorded.'], 204);
    }
}
