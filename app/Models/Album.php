<?php
namespace App\Models;

use App\Models\Concerns\HasArt;
use Illuminate\Database\Eloquent\Model;

class Album extends Model
{
    use HasArt;
    protected $fillable = ['artist_id', 'record_label_id', 'title', 'cover', 'release_year'];

    public function artist()      { return $this->belongsTo(Artist::class); }
    public function recordLabel() { return $this->belongsTo(RecordLabel::class); }
    public function songs()       { return $this->hasMany(Song::class); }
}
