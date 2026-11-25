<?php
namespace App\Models;

use App\Models\Concerns\HasArt;
use Illuminate\Database\Eloquent\Model;

class Genre extends Model
{
    use HasArt;
    protected $fillable = ['name'];
    public function songs() { return $this->hasMany(Song::class); }
}
