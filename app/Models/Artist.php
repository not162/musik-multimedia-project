<?php
namespace App\Models;

use App\Models\Concerns\HasArt;
use Illuminate\Database\Eloquent\Model;

class Artist extends Model
{
    use HasArt;
    protected $artColumn = 'photo';
    protected $fillable = ['name', 'bio', 'photo'];

    public function albums() { return $this->hasMany(Album::class); }
    public function songs()  { return $this->hasMany(Song::class); }
}
