<?php
namespace App\Models;

use App\Models\Concerns\HasArt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Song extends Model
{
    use HasArt;

    protected $fillable = ['artist_id','album_id','genre_id','title','file_path','cover','lyrics','duration','play_count'];

    public function artist()    { return $this->belongsTo(Artist::class); }
    public function album()     { return $this->belongsTo(Album::class); }
    public function genre()     { return $this->belongsTo(Genre::class); }
    public function histories() { return $this->hasMany(PlayHistory::class); }
    public function favoritedBy() { return $this->belongsToMany(User::class, 'favorites')->withTimestamps(); }

    // Cover lagu; kalau kosong pakai cover album
    public function getArtUrlAttribute(): ?string
    {
        $path = $this->cover ?: $this->album?->cover;
        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function getDurationLabelAttribute(): string
    {
        return intdiv($this->duration, 60) . ':' . str_pad($this->duration % 60, 2, '0', STR_PAD_LEFT);
    }

    public function scopeSearch($q, ?string $term)
    {
        return $term ? $q->where(function ($w) use ($term) {
            $w->where('title', 'like', "%{$term}%")
              ->orWhereHas('artist', fn ($a) => $a->where('name', 'like', "%{$term}%"))
              ->orWhereHas('album',  fn ($a) => $a->where('title', 'like', "%{$term}%"));
        }) : $q;
    }
}
