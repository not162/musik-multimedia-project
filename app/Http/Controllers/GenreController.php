<?php
namespace App\Http\Controllers;

use App\Models\Genre;

class GenreController extends Controller
{
    public function show(Genre $genre)
    {
        return view('genres.show', [
            'genre' => $genre,
            'songs' => $genre->songs()->with(['artist', 'album'])->orderByDesc('play_count')->paginate(20),
        ]);
    }
}
