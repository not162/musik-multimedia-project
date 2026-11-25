<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\SongPlayedEvent;
use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Services\LrsService;
use App\Support\SafeBroadcast;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Cache;

class SongController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'api:songs:' . md5($request->fullUrl());

        $songs = Cache::remember($cacheKey, 120, function () use ($request) {
            return Song::with(['artist:id,name', 'album:id,title,cover', 'genre:id,name'])
                ->search($request->query('q'))
                ->when($request->query('genre'), fn ($q, $g) => $q->where('genre_id', $g))
                ->when($request->query('artist'), fn ($q, $a) => $q->where('artist_id', $a))
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
        $song->load(['artist', 'album', 'genre']);

        $related = Cache::remember("api:song:{$song->id}:related", 300, function () use ($song) {
            return Song::with(['artist:id,name'])
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

    public function stream(Song $song)
    {
        $path = storage_path('app/public/' . $song->file_path);
        abort_unless(file_exists($path), 404, 'File not found.');

        $mime = str_ends_with(strtolower($path), '.wav') ? 'audio/wav' : 'audio/mpeg';

        return response()->file($path, [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
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
