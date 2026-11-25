<?php
/**
 * Phase 4: Local Record Store (LRS Local) Service
 * Persists tamper-evident activity statements in MySQL + local append-only JSONL file.
 */

namespace App\Services;

use App\Models\LrsStatement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LrsService
{
    public static function record(
        string  $verb,
        string  $objectType,
        ?int    $objectId = null,
        ?string $objectName = null,
        array   $context = [],
    ): ?LrsStatement {
        try {
            $user = auth()->user();
            $req  = request();
            $uuid = (string) Str::uuid();

            $payload = [
                'statement_id' => $uuid,
                'user_id'      => $user?->id,
                'verb'         => $verb,
                'object_type'  => $objectType,
                'object_id'    => $objectId,
                'object_name'  => $objectName,
            ];

            $signature = LrsStatement::signPayload($payload);

            $stmt = LrsStatement::create([
                'statement_id' => $uuid,
                'user_id'      => $user?->id,
                'actor_name'   => $user?->name ?? 'Tamu Publik',
                'actor_email'  => $user?->email,
                'verb'         => $verb,
                'object_type'  => $objectType,
                'object_id'    => $objectId,
                'object_name'  => $objectName,
                'context'      => array_merge($context, [
                    'role'       => $user?->role ?? 'guest',
                    'user_agent' => substr((string) $req?->userAgent(), 0, 180),
                ]),
                'ip_address'   => $req?->ip(),
                'signature'    => $signature,
                'stored_at'    => now(),
            ]);

            // Mirror to local append-only LRS JSONL store
            $dir = storage_path('app/lrs');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $line = json_encode([
                'id'        => $uuid,
                'actor'     => ['id' => $user?->id, 'name' => $stmt->actor_name, 'role' => $user?->role ?? 'guest'],
                'verb'      => $verb,
                'object'    => ['type' => $objectType, 'id' => $objectId, 'name' => $objectName],
                'context'   => $context,
                'ip'        => $stmt->ip_address,
                'signature' => $signature,
                'stored_at' => $stmt->stored_at?->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL;

            @file_put_contents($dir . '/statements.jsonl', $line, FILE_APPEND | LOCK_EX);

            return $stmt;
        } catch (\Throwable $e) {
            Log::channel('security')->warning('LRS Local write skipped: ' . $e->getMessage());
            return null;
        }
    }
}
