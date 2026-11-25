<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Album, AuditLog, Artist};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Storage;

class AlbumController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $albums = Album::with('artist:id,name')->withCount('songs')
            ->when($request->query('q'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)->withQueryString();

        return response()->json($albums);
    }

    public function show(Album $album): JsonResponse
    {
        return response()->json(['album' => $album->load('artist')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $album = Album::create($data);

        AuditLog::record('create', 'Album', $album->id, newValues: $data, description: "Created album: {$album->title}");

        return response()->json(['message' => 'Album ditambahkan.', 'album' => $album], 201);
    }

    public function update(Request $request, Album $album): JsonResponse
    {
        $old  = $album->toArray();
        $data = $this->validated($request, $album);
        $album->update($data);

        AuditLog::record('update', 'Album', $album->id, $old, $data, "Updated album: {$album->title}");

        return response()->json(['message' => 'Album diperbarui.', 'album' => $album->fresh()]);
    }

    public function destroy(Album $album): JsonResponse
    {
        if ($album->cover) Storage::disk('public')->delete($album->cover);

        AuditLog::record('delete', 'Album', $album->id, $album->toArray(), description: "Deleted album: {$album->title}");
        $album->delete();

        return response()->json(['message' => 'Album dihapus.']);
    }

    private function validated(Request $request, ?Album $album = null): array
    {
        $data = $request->validate([
            'title'        => 'required|string|max:150',
            'artist_id'    => 'required|exists:artists,id',
            'release_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'cover'        => 'nullable|image|max:2048',
        ]);

        unset($data['cover']);

        if ($request->hasFile('cover')) {
            if ($album?->cover) Storage::disk('public')->delete($album->cover);
            $data['cover'] = $request->file('cover')->store('covers', 'public');
        }

        return $data;
    }
}
