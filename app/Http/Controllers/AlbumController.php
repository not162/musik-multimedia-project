<?php
namespace App\Http\Controllers;

use App\Models\Album;

class AlbumController extends Controller
{
    public function show(Album $album)
    {
        $album->load('artist');
        return view('albums.show', [
            'album' => $album,
            'songs' => $album->songs()->with(['artist', 'album'])->orderBy('id')->get(),
        ]);
    }
}
