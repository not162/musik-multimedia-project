<?php
/**
 * Phase 7: SEO Metadata Controller — Admin only
 */

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoMetadata;
use Illuminate\Http\{JsonResponse, Request};

class SeoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => SeoMetadata::orderBy('route_name')->get()]);
    }

    public function show(SeoMetadata $seoMetadata): JsonResponse
    {
        return response()->json(['seo' => $seoMetadata]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'route_name'  => 'required|string|max:100|unique:seo_metadata,route_name',
            'title'       => 'nullable|string|max:160',
            'description' => 'nullable|string|max:320',
            'og_image'    => 'nullable|string|max:255',
            'extra_meta'  => 'nullable|array',
        ]);

        $seo = SeoMetadata::create($data);

        return response()->json(['message' => 'SEO metadata disimpan.', 'seo' => $seo], 201);
    }

    public function update(Request $request, SeoMetadata $seoMetadata): JsonResponse
    {
        $data = $request->validate([
            'title'       => 'nullable|string|max:160',
            'description' => 'nullable|string|max:320',
            'og_image'    => 'nullable|string|max:255',
            'extra_meta'  => 'nullable|array',
        ]);

        $seoMetadata->update($data);

        return response()->json(['message' => 'SEO metadata diperbarui.', 'seo' => $seoMetadata->fresh()]);
    }

    public function destroy(SeoMetadata $seoMetadata): JsonResponse
    {
        $seoMetadata->delete();

        return response()->json(['message' => 'SEO metadata dihapus.']);
    }
}
