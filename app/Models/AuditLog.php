<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'resource_type', 'resource_id',
        'ip_address', 'user_agent', 'old_values', 'new_values', 'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Polymorphic: resource that was acted on.
     */
    public function resource()
    {
        return $this->morphTo('resource', 'resource_type', 'resource_id');
    }

    // ── Convenience factory ──────────────────────────────────────────

    public static function record(
        string  $action,
        ?string $resourceType = null,
        ?int    $resourceId = null,
        ?array  $oldValues = null,
        ?array  $newValues = null,
        ?string $description = null,
    ): static {
        $request = request();

        return static::create([
            'user_id'       => auth()->id(),
            'action'        => $action,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'ip_address'    => $request?->ip(),
            'user_agent'    => $request?->userAgent(),
            'old_values'    => $oldValues,
            'new_values'    => $newValues,
            'description'   => $description,
        ]);
    }
}
