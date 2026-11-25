<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserNotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\{Playlist, Song};
use App\Services\LrsService;
use App\Support\SafeBroadcast;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;

class PlaylistController extends Controller
{
    private function own(Playlist $playlist): void
    {
        abort_unless($playlist->user_id === auth()->id(), 403, 'Bukan playlist Anda.');
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => auth()->user()->playlists()->withCount('songs')->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'is_public' => 'boolean',
        ]);

        $playlist = $request->user()->playlists()->create($data);
        LrsService::record('created_playlist', 'Playlist', $playlist->id, $playlist->name, ['channel' => 'api']);

        return response()->json([
            'message'  => 'Playlist dibuat.',
            'playlist' => $playlist,
        ], 201);
    }

    public function show(Playlist $playlist): JsonResponse
    {
        $this->own($playlist);

        return response()->json([
            'playlist' => $playlist,
            'songs'    => $playlist->songs()->with(['artist:id,name', 'album:id,title'])->get(),
        ]);
    }

    public function update(Request $request, Playlist $playlist): JsonResponse
    {
        $this->own($playlist);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'is_public' => 'sometimes|boolean',
        ]);

        $playlist->update($data);

        return response()->json([
            'message'  => 'Playlist diperbarui.',
            'playlist' => $playlist->fresh(),
        ]);
    }

    public function destroy(Playlist $playlist): JsonResponse
    {
        $this->own($playlist);
        $playlist->delete();

        return response()->json(['message' => 'Playlist dihapus.']);
    }

    public function addSong(Playlist $playlist, Song $song): JsonResponse
    {
        $this->own($playlist);

        if ($playlist->songs()->where('songs.id', $song->id)->exists()) {
            return response()->json(['message' => 'Lagu sudah ada di playlist.'], 409);
        }

        $pos = (int) DB::table('playlist_song')->where('playlist_id', $playlist->id)->max('position');
        $playlist->songs()->attach($song->id, ['position' => $pos + 1]);

        LrsService::record('added_to_playlist', 'Playlist', $playlist->id, "{$playlist->name} <- {$song->title}", ['channel' => 'api']);
        SafeBroadcast::dispatch(new UserNotificationEvent(
            auth()->id(),
            'Playlist Diperbarui',
            "\"{$song->title}\" masuk ke playlist \"{$playlist->name}\".",
            'playlist'
        ));

        return response()->json(['message' => "Ditambahkan ke \"{$playlist->name}\"."]);
    }

    public function removeSong(Playlist $playlist, Song $song): JsonResponse
    {
        $this->own($playlist);
        $playlist->songs()->detach($song->id);

        return response()->json(['message' => 'Lagu dihapus dari playlist.']);
    }
}
