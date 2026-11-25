<?php
namespace App\Http\Controllers;

use App\Events\UserNotificationEvent;
use App\Models\{Playlist, Song};
use App\Services\LrsService;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlaylistController extends Controller
{
    private function own(Playlist $playlist): void
    {
        abort_unless($playlist->user_id === auth()->id(), 403);
    }

    public function index()
    {
        return view('playlists.index', [
            'playlists' => auth()->user()->playlists()->withCount('songs')->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        $playlist = $request->user()->playlists()->create($data);

        LrsService::record('created_playlist', 'Playlist', $playlist->id, $playlist->name);

        return redirect()->route('playlists.show', $playlist)->with('ok', 'Playlist dibuat.');
    }

    public function show(Playlist $playlist)
    {
        $this->own($playlist);
        $songs = $playlist->songs()->with(['artist', 'album'])->get();
        return view('playlists.show', ['playlist' => $playlist, 'songs' => $songs, 'owner' => true]);
    }

    public function publicShow(Playlist $playlist)
    {
        $isOwner = auth()->id() === $playlist->user_id;
        abort_unless($playlist->is_public || $isOwner, 404);
        $playlist->load('user');
        $songs = $playlist->songs()->with(['artist', 'album'])->get();
        return view('playlists.show', ['playlist' => $playlist, 'songs' => $songs, 'owner' => false]);
    }

    public function update(Request $request, Playlist $playlist)
    {
        $this->own($playlist);
        $data = $request->validate(['name' => 'required|string|max:100']);
        $playlist->update(['name' => $data['name'], 'is_public' => $request->boolean('is_public')]);
        return back()->with('ok', 'Playlist diperbarui.');
    }

    public function destroy(Playlist $playlist)
    {
        $this->own($playlist);
        $playlist->delete();
        return redirect()->route('playlists.index')->with('ok', 'Playlist dihapus.');
    }

    public function addSong(Request $request, Playlist $playlist, Song $song)
    {
        $this->own($playlist);

        $exists = $playlist->songs()->where('songs.id', $song->id)->exists();
        if (! $exists) {
            $pos = (int) DB::table('playlist_song')->where('playlist_id', $playlist->id)->max('position');
            $playlist->songs()->attach($song->id, ['position' => $pos + 1]);
            LrsService::record('added_to_playlist', 'Playlist', $playlist->id, "{$playlist->name} <- {$song->title}");
            SafeBroadcast::dispatch(new UserNotificationEvent(
                auth()->id(),
                'Playlist Diperbarui',
                "\"{$song->title}\" ditambahkan ke playlist \"{$playlist->name}\".",
                'playlist'
            ));
        }
        $msg = $exists ? 'Lagu sudah ada di playlist.' : "Ditambahkan ke “{$playlist->name}”.";

        return $request->expectsJson() ? response()->json(['message' => $msg]) : back()->with('ok', $msg);
    }

    public function removeSong(Playlist $playlist, Song $song)
    {
        $this->own($playlist);
        $playlist->songs()->detach($song->id);
        return back()->with('ok', 'Lagu dihapus dari playlist.');
    }
}
