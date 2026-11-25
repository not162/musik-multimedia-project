<?php
/**
 * Phase 9: Error Logs Controller — Admin only
 */

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use Illuminate\Http\{JsonResponse, Request};

class ErrorLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = ErrorLog::with('user:id,name')
            ->when($request->query('level'), fn ($q, $l) => $q->where('level', $l))
            ->when($request->query('channel'), fn ($q, $c) => $q->where('channel', $c))
            ->when($request->has('resolved'), fn ($q) => $q->where('is_resolved', $request->boolean('resolved')))
            ->when($request->query('hours'), fn ($q, $h) => $q->recent((int) $h))
            ->latest()
            ->paginate(25)->withQueryString();

        return response()->json($logs);
    }

    public function show(ErrorLog $errorLog): JsonResponse
    {
        return response()->json([
            'error_log' => $errorLog->load(['user:id,name,email', 'resolver:id,name']),
        ]);
    }

    public function resolve(ErrorLog $errorLog): JsonResponse
    {
        $errorLog->markResolved();

        return response()->json([
            'message'   => 'Error ditandai resolved.',
            'error_log' => $errorLog->fresh()->load('resolver:id,name'),
        ]);
    }

    public function destroy(ErrorLog $errorLog): JsonResponse
    {
        $errorLog->delete();

        return response()->json(['message' => 'Log error dihapus.']);
    }
}
