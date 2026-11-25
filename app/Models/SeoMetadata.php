<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMetadata extends Model
{
    protected $table = 'seo_metadata';

    protected $fillable = ['route_name', 'title', 'description', 'og_image', 'extra_meta'];

    protected $casts = ['extra_meta' => 'array'];
}
