<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ChartController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Cache::remember('api:charts', 300, function () {
            return [
                'weekly' => Song::with(['artist:id,name', 'album:id,title'])
                    ->withCount(['histories as week_plays' => fn ($q) => $q->where('played_at', '>=', now()->subDays(7))])
                    ->orderByDesc('week_plays')->orderByDesc('play_count')->limit(20)->get(),
                'all_time' => Song::with(['artist:id,name', 'album:id,title'])
                    ->orderByDesc('play_count')->limit(20)->get(),
            ];
        });

        return response()->json($data);
    }
}
