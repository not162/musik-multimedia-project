<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RecordLabel;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Cache;

class LabelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $labels = Cache::remember('api:labels:' . md5($request->fullUrl()), 180, function () use ($request) {
            return RecordLabel::withCount(['songs', 'albums'])
                ->when($request->query('q'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
                ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
                ->orderByDesc('is_featured')
                ->latest()
                ->paginate(min((int) $request->query('per_page', 20), 100))
                ->withQueryString();
        });

        return response()->json($labels);
    }

    public function show(RecordLabel $label): JsonResponse
    {
        return response()->json([
            'label'  => $label->loadCount(['songs', 'albums']),
            'songs'  => $label->songs()->with(['artist:id,name', 'album:id,title', 'genre:id,name'])->orderByDesc('play_count')->get(),
            'albums' => $label->albums()->with('artist:id,name')->get(),
        ]);
    }
}
