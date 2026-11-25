<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\CatalogUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Song};
use App\Support\SafeBroadcast;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Cache, Storage};

class SongController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $songs = Song::with(['artist:id,name', 'genre:id,name'])
            ->search($request->query('q'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return response()->json($songs);
    }

    public function show(Song $song): JsonResponse
    {
        return response()->json(['song' => $song->load(['artist', 'album', 'genre'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $song = Song::create($data);

        AuditLog::record('create', 'Song', $song->id, newValues: $data, description: "Created song: {$song->title}");
        $this->bustAndNotify('created', $song);

        return response()->json(['message' => 'Lagu diunggah.', 'song' => $song], 201);
    }

    public function update(Request $request, Song $song): JsonResponse
    {
        $old  = $song->toArray();
        $data = $this->validated($request, $song);
        $song->update($data);

        AuditLog::record('update', 'Song', $song->id, $old, $data, "Updated song: {$song->title}");
        $this->bustAndNotify('updated', $song);

        return response()->json(['message' => 'Lagu diperbarui.', 'song' => $song->fresh()]);
    }

    public function destroy(Song $song): JsonResponse
    {
        Storage::disk('public')->delete(array_filter([$song->file_path, $song->cover]));
        AuditLog::record('delete', 'Song', $song->id, $song->toArray(), description: "Deleted song: {$song->title}");

        $id = $song->id;
        $title = $song->title;
        $song->delete();

        Cache::forget('api:charts');
        Cache::forget('api:genres');
        Cache::forget('home:stats');
        Cache::forget('seo:sitemap:xml');
        SafeBroadcast::dispatch(new CatalogUpdatedEvent('deleted', 'song', $id, $title));

        return response()->json(['message' => 'Lagu dihapus.']);
    }

    private function validated(Request $request, ?Song $song = null): array
    {
        $data = $request->validate([
            'title'     => 'required|string|max:150',
            'artist_id' => 'required|exists:artists,id',
            'album_id'  => 'nullable|exists:albums,id',
            'genre_id'  => 'nullable|exists:genres,id',
            'duration'  => 'nullable|integer|min:0|max:36000',
            'lyrics'    => 'nullable|string|max:20000',
            'audio'     => ($song ? 'nullable' : 'required') . '|file|mimes:mp3,wav,ogg,m4a,aac,flac|max:30720',
            'cover'     => 'nullable|image|max:2048',
        ]);

        unset($data['audio'], $data['cover']);
        $data['duration'] = (int) ($data['duration'] ?? 0);

        if ($request->hasFile('audio')) {
            if ($song) Storage::disk('public')->delete($song->file_path);
            $data['file_path'] = $request->file('audio')->store('songs', 'public');
        }

        if ($request->hasFile('cover')) {
            if ($song?->cover) Storage::disk('public')->delete($song->cover);
            $data['cover'] = $request->file('cover')->store('covers', 'public');
        }

        return $data;
    }

    private function bustAndNotify(string $action, Song $song): void
    {
        Cache::forget('api:charts');
        Cache::forget('api:genres');
        Cache::forget('home:stats');
        Cache::forget('seo:sitemap:xml');
        SafeBroadcast::dispatch(new CatalogUpdatedEvent($action, 'song', $song->id, $song->title));
    }
}
