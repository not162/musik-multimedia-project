<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiEndpointRule extends Model
{
    protected $fillable = [
        'group_name', 'endpoint_key', 'uri_pattern', 'method',
        'rate_limit_per_min', 'is_enabled', 'realtime_broadcast', 'description',
    ];

    protected $casts = [
        'is_enabled'         => 'boolean',
        'realtime_broadcast' => 'boolean',
        'rate_limit_per_min' => 'integer',
    ];
}
