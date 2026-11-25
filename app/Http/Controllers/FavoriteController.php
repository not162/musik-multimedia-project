<?php
namespace App\Http\Controllers;

use App\Events\UserNotificationEvent;
use App\Models\Song;
use App\Services\LrsService;
use App\Support\SafeBroadcast;

class FavoriteController extends Controller
{
    public function index()
    {
        $songs = auth()->user()->favoriteSongs()->with(['artist', 'album', 'genre'])->orderByPivot('created_at', 'desc')->get();
        return view('library.favorites', compact('songs'));
    }

    public function toggle(Song $song)
    {
        $res = auth()->user()->favoriteSongs()->toggle($song->id);
        $fav = count($res['attached']) > 0;

        LrsService::record($fav ? 'favorited' : 'unfavorited', 'Song', $song->id, $song->title);
        if ($fav) {
            SafeBroadcast::dispatch(new UserNotificationEvent(
                auth()->id(),
                'Favorit Diperbarui',
                "\"{$song->title}\" disimpan ke daftar favorit Anda.",
                'favorite'
            ));
        }

        return response()->json(['favorited' => $fav]);
    }
}
