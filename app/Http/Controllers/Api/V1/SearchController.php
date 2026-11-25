<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Album, Artist, Song};
use Illuminate\Http\{JsonResponse, Request};

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        if ($q === '') {
            return response()->json(['songs' => [], 'artists' => [], 'albums' => []]);
        }

        return response()->json([
            'songs'   => Song::with(['artist:id,name', 'album:id,title'])->search($q)->orderByDesc('play_count')->limit(30)->get(),
            'artists' => Artist::where('name', 'like', "%{$q}%")->limit(8)->get(),
            'albums'  => Album::with('artist:id,name')->where('title', 'like', "%{$q}%")->limit(8)->get(),
        ]);
    }
}
