<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 4: Local Record Store (LRS Local) Statement Model
 * Stores xAPI-style activity records locally in MySQL & JSONL with HMAC-SHA256 integrity.
 */
class LrsStatement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'statement_id', 'user_id', 'actor_name', 'actor_email',
        'verb', 'object_type', 'object_id', 'object_name',
        'context', 'ip_address', 'signature', 'stored_at',
    ];

    protected $casts = [
        'context'   => 'array',
        'stored_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function signPayload(array $payload): string
    {
        $secret = (string) config('app.key');
        ksort($payload);
        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE), $secret);
    }

    public function verifySignature(): bool
    {
        $payload = [
            'statement_id' => $this->statement_id,
            'user_id'      => $this->user_id,
            'verb'         => $this->verb,
            'object_type'  => $this->object_type,
            'object_id'    => $this->object_id,
            'object_name'  => $this->object_name,
        ];

        return hash_equals($this->signature, static::signPayload($payload));
    }
}
