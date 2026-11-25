<?php
/**
 * Phase 4: Audit Logs & LRS Local Controller — Admin only
 */

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, LrsStatement};
use Illuminate\Http\{JsonResponse, Request};

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::with('user:id,name,email')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->when($request->query('user_id'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('resource_type'), fn ($q, $r) => $q->where('resource_type', $r))
            ->when($request->query('from'), fn ($q, $f) => $q->where('created_at', '>=', $f))
            ->when($request->query('to'), fn ($q, $t) => $q->where('created_at', '<=', $t))
            ->latest('created_at')
            ->paginate(30)->withQueryString();

        return response()->json($logs);
    }

    public function lrs(Request $request): JsonResponse
    {
        $statements = LrsStatement::with('user:id,name,email')
            ->when($request->query('verb'), fn ($q, $v) => $q->where('verb', $v))
            ->when($request->query('object_type'), fn ($q, $t) => $q->where('object_type', $t))
            ->latest('stored_at')
            ->paginate(30)->withQueryString();

        $statements->getCollection()->transform(function ($stmt) {
            $stmt->signature_valid = $stmt->verifySignature();
            return $stmt;
        });

        return response()->json($statements);
    }
}
