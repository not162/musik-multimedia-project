<?php
namespace App\Http\Controllers;

use App\Models\Artist;

class ArtistController extends Controller
{
    public function show(Artist $artist)
    {
        return view('artists.show', [
            'artist' => $artist,
            'songs'  => $artist->songs()->with(['artist', 'album'])->orderByDesc('play_count')->get(),
            'albums' => $artist->albums()->orderByDesc('release_year')->get(),
        ]);
    }
}
