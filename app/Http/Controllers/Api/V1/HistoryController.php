<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HistoryController extends Controller
{
    public function index(): JsonResponse
    {
        $items = auth()->user()->playHistories()
            ->with('song.artist:id,name', 'song.album:id,title')
            ->whereHas('song')
            ->latest('played_at')
            ->paginate(30);

        return response()->json($items);
    }

    public function clear(): JsonResponse
    {
        auth()->user()->playHistories()->delete();

        return response()->json(['message' => 'Riwayat dihapus.']);
    }
}
