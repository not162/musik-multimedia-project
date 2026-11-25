<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Cache;

class ArtistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $artists = Cache::remember('api:artists:' . md5($request->fullUrl()), 300, function () use ($request) {
            return Artist::withCount('songs')
                ->when($request->query('q'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
                ->orderBy('name')
                ->paginate(min((int) $request->query('per_page', 20), 100))
                ->withQueryString();
        });

        return response()->json($artists);
    }

    public function show(Artist $artist): JsonResponse
    {
        return response()->json([
            'artist' => $artist->loadCount('songs'),
            'songs'  => $artist->songs()->with('album:id,title')->orderByDesc('play_count')->limit(50)->get(),
            'albums' => $artist->albums()->withCount('songs')->orderByDesc('release_year')->get(),
        ]);
    }
}
