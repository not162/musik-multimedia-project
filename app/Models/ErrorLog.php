<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'level', 'channel', 'message', 'context', 'stack_trace',
        'url', 'method', 'ip_address', 'user_id',
        'is_resolved', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeUnresolved($q)
    {
        return $q->where('is_resolved', false);
    }

    public function scopeLevel($q, string $level)
    {
        return $q->where('level', $level);
    }

    public function scopeRecent($q, int $hours = 24)
    {
        return $q->where('created_at', '>=', now()->subHours($hours));
    }

    // ── Helper ───────────────────────────────────────────────────────

    public function markResolved(): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);
    }
}
