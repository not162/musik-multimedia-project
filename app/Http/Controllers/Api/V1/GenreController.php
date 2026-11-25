<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Cache;

class GenreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $genres = Cache::remember('api:genres', 600, function () {
            return Genre::withCount('songs')->orderBy('name')->get();
        });

        return response()->json(['data' => $genres]);
    }

    public function show(Genre $genre): JsonResponse
    {
        return response()->json([
            'genre' => $genre,
            'songs' => $genre->songs()->with(['artist:id,name', 'album:id,title'])
                ->orderByDesc('play_count')->paginate(20),
        ]);
    }
}
