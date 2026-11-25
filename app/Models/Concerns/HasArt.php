<?php
namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/** Cover/foto + gradient cadangan + inisial untuk tampilan kartu. */
trait HasArt
{
    public function getArtUrlAttribute(): ?string
    {
        $col  = $this->artColumn ?? 'cover';
        $path = $this->{$col};
        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function getGradientAttribute(): string
    {
        $h = crc32($this->getTable() . $this->getKey()) % 360;
        return "linear-gradient(135deg,hsl({$h} 60% 42%),hsl(" . (($h + 55) % 360) . " 60% 24%))";
    }

    public function getInitialAttribute(): string
    {
        $t = $this->title ?? $this->name ?? '?';
        return mb_strtoupper(mb_substr($t, 0, 1));
    }
}
