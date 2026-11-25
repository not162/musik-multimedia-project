<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\Request;

class GenreController extends Controller
{
    public function index()
    {
        return view('admin.genres', ['genres' => Genre::withCount('songs')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['name' => 'required|string|max:60|unique:genres,name']);
        Genre::create($d);
        return back()->with('ok', 'Genre ditambahkan.');
    }

    public function destroy(Genre $genre)
    {
        $genre->delete();
        return back()->with('ok', 'Genre dihapus.');
    }
}
