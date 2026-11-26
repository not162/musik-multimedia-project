<?php

namespace App\Models;

use App\Models\Concerns\HasArt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RecordLabel extends Model
{
    use HasArt;

    protected $artColumn = 'logo';

    protected $fillable = [
        'name', 'slug', 'country', 'founded_year',
        'website', 'description', 'logo', 'is_featured',
    ];

    protected $casts = [
        'is_featured'  => 'boolean',
        'founded_year' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $label) {
            if (empty($label->slug)) {
                $label->slug = Str::slug($label->name) . '-' . Str::lower(Str::random(4));
            }
        });
    }

    public function songs()
    {
        return $this->hasMany(Song::class);
    }

    public function albums()
    {
        return $this->hasMany(Album::class);
    }
}
