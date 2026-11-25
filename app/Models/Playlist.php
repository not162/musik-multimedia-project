<?php
namespace App\Models;

use App\Models\Concerns\HasArt;
use Illuminate\Database\Eloquent\Model;

class Playlist extends Model
{
    use HasArt;
    protected $fillable = ['user_id', 'name', 'is_public'];
    protected $casts = ['is_public' => 'boolean'];

    public function user()  { return $this->belongsTo(User::class); }
    public function songs() { return $this->belongsToMany(Song::class)->withPivot('position')->orderBy('playlist_song.position'); }
}
