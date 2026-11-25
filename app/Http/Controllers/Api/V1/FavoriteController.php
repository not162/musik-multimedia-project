<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserNotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Services\LrsService;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    public function index(): JsonResponse
    {
        $songs = auth()->user()->favoriteSongs()
            ->with(['artist:id,name', 'album:id,title'])
            ->orderByPivot('created_at', 'desc')->get();

        return response()->json(['data' => $songs]);
    }

    public function toggle(Song $song): JsonResponse
    {
        $result = auth()->user()->favoriteSongs()->toggle($song->id);
        $fav = count($result['attached']) > 0;

        LrsService::record($fav ? 'favorited' : 'unfavorited', 'Song', $song->id, $song->title, ['channel' => 'api']);
        if ($fav) {
            SafeBroadcast::dispatch(new UserNotificationEvent(
                auth()->id(),
                'Favorit Diperbarui',
                "\"{$song->title}\" disimpan ke favorit.",
                'favorite'
            ));
        }

        return response()->json([
            'favorited' => $fav,
            'message'   => $fav ? 'Ditambahkan ke favorit.' : 'Dihapus dari favorit.',
        ]);
    }
}
