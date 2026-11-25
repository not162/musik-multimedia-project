<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Artist};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Storage;

class ArtistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $artists = Artist::withCount('songs')
            ->when($request->query('q'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(20)->withQueryString();

        return response()->json($artists);
    }

    public function show(Artist $artist): JsonResponse
    {
        return response()->json(['artist' => $artist->loadCount('songs')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $artist = Artist::create($data);

        AuditLog::record('create', 'Artist', $artist->id, newValues: $data, description: "Created artist: {$artist->name}");

        return response()->json(['message' => 'Artis ditambahkan.', 'artist' => $artist], 201);
    }

    public function update(Request $request, Artist $artist): JsonResponse
    {
        $old  = $artist->toArray();
        $data = $this->validated($request, $artist);
        $artist->update($data);

        AuditLog::record('update', 'Artist', $artist->id, $old, $data, "Updated artist: {$artist->name}");

        return response()->json(['message' => 'Artis diperbarui.', 'artist' => $artist->fresh()]);
    }

    public function destroy(Artist $artist): JsonResponse
    {
        foreach ($artist->songs as $s) {
            Storage::disk('public')->delete(array_filter([$s->file_path, $s->cover]));
        }
        foreach ($artist->albums as $a) {
            if ($a->cover) Storage::disk('public')->delete($a->cover);
        }
        if ($artist->photo) Storage::disk('public')->delete($artist->photo);

        AuditLog::record('delete', 'Artist', $artist->id, $artist->toArray(), description: "Deleted artist: {$artist->name}");
        $artist->delete();

        return response()->json(['message' => 'Artis dihapus beserta lagu & album-nya.']);
    }

    private function validated(Request $request, ?Artist $artist = null): array
    {
        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'bio'   => 'nullable|string|max:2000',
            'photo' => 'nullable|image|max:2048',
        ]);

        unset($data['photo']);

        if ($request->hasFile('photo')) {
            if ($artist?->photo) Storage::disk('public')->delete($artist->photo);
            $data['photo'] = $request->file('photo')->store('artists', 'public');
        }

        return $data;
    }
}
