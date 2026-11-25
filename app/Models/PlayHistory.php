<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayHistory extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'song_id', 'played_at'];
    protected $casts = ['played_at' => 'datetime'];

    public function song() { return $this->belongsTo(Song::class); }
    public function user() { return $this->belongsTo(User::class); }
}
