<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Genre};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Cache;

class GenreController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Genre::withCount('songs')->orderBy('name')->get(),
        ]);
    }

    public function show(Genre $genre): JsonResponse
    {
        return response()->json(['genre' => $genre->loadCount('songs')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:60|unique:genres,name']);
        $genre = Genre::create($data);

        AuditLog::record('create', 'Genre', $genre->id, newValues: $data);
        Cache::forget('api:genres');

        return response()->json(['message' => 'Genre ditambahkan.', 'genre' => $genre], 201);
    }

    public function update(Request $request, Genre $genre): JsonResponse
    {
        $old  = $genre->toArray();
        $data = $request->validate(['name' => 'required|string|max:60|unique:genres,name,' . $genre->id]);
        $genre->update($data);

        AuditLog::record('update', 'Genre', $genre->id, $old, $data);
        Cache::forget('api:genres');

        return response()->json(['message' => 'Genre diperbarui.', 'genre' => $genre->fresh()]);
    }

    public function destroy(Genre $genre): JsonResponse
    {
        AuditLog::record('delete', 'Genre', $genre->id, $genre->toArray());
        $genre->delete();
        Cache::forget('api:genres');

        return response()->json(['message' => 'Genre dihapus.']);
    }
}
