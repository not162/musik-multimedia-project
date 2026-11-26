<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\CatalogUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, RecordLabel};
use App\Support\SafeBroadcast;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Cache, Storage};
use Illuminate\Support\Str;

class LabelController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            RecordLabel::withCount(['songs', 'albums'])->latest()->paginate(25)
        );
    }

    public function show(RecordLabel $label): JsonResponse
    {
        return response()->json(['label' => $label->loadCount(['songs', 'albums'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'required|string|max:120',
            'country'      => 'nullable|string|max:80',
            'founded_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'website'      => 'nullable|url|max:255',
            'description'  => 'nullable|string|max:2000',
            'is_featured'  => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']) . '-' . Str::lower(Str::random(4));
        $label = RecordLabel::create($data);

        AuditLog::record('create', 'RecordLabel', $label->id, newValues: ['name' => $label->name]);
        Cache::forget('home:labels');
        Cache::forget('seo:sitemap:xml');
        SafeBroadcast::dispatch(new CatalogUpdatedEvent('created', 'label', $label->id, $label->name));

        return response()->json(['message' => 'Label musik dibuat.', 'label' => $label], 201);
    }

    public function update(Request $request, RecordLabel $label): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:120',
            'country'      => 'nullable|string|max:80',
            'founded_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'website'      => 'nullable|url|max:255',
            'description'  => 'nullable|string|max:2000',
            'is_featured'  => 'boolean',
        ]);

        $label->update($data);
        AuditLog::record('update', 'RecordLabel', $label->id, newValues: $data);
        Cache::forget('home:labels');
        SafeBroadcast::dispatch(new CatalogUpdatedEvent('updated', 'label', $label->id, $label->name));

        return response()->json(['message' => 'Label musik diperbarui.', 'label' => $label->fresh()]);
    }

    public function destroy(RecordLabel $label): JsonResponse
    {
        if ($label->logo) Storage::disk('public')->delete($label->logo);
        AuditLog::record('delete', 'RecordLabel', $label->id, ['name' => $label->name]);
        $id = $label->id;
        $name = $label->name;
        $label->delete();

        Cache::forget('home:labels');
        SafeBroadcast::dispatch(new CatalogUpdatedEvent('deleted', 'label', $id, $name));

        return response()->json(['message' => 'Label musik dihapus.']);
    }
}
