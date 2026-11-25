<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Album;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Cache;

class AlbumController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $albums = Cache::remember('api:albums:' . md5($request->fullUrl()), 300, function () use ($request) {
            return Album::with('artist:id,name')->withCount('songs')
                ->when($request->query('q'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
                ->latest()
                ->paginate(min((int) $request->query('per_page', 20), 100))
                ->withQueryString();
        });

        return response()->json($albums);
    }

    public function show(Album $album): JsonResponse
    {
        return response()->json([
            'album' => $album->load('artist'),
            'songs' => $album->songs()->with('artist:id,name')->orderBy('id')->get(),
        ]);
    }
}
