<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Album, Artist, RecordLabel};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AlbumController extends Controller
{
    public function index(Request $request)
    {
        $albums = Album::with('artist')->withCount('songs')
            ->when($request->query('q'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest()->paginate(20)->withQueryString();
        return view('admin.albums.index', compact('albums'));
    }

    public function create() { return view('admin.albums.form', ['album' => new Album, 'artists' => Artist::orderBy('name')->get(), 'labels' => RecordLabel::orderBy('name')->get()]); }
    public function edit(Album $album) { return view('admin.albums.form', ['album' => $album, 'artists' => Artist::orderBy('name')->get(), 'labels' => RecordLabel::orderBy('name')->get()]); }

    private function data(Request $request, ?Album $album = null): array
    {
        $d = $request->validate([
            'title'           => 'required|string|max:150',
            'artist_id'       => 'required|exists:artists,id',
            'record_label_id' => 'nullable|exists:record_labels,id',
            'release_year'    => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'cover'           => 'nullable|image|max:2048',
        ]);
        unset($d['cover']);
        if ($request->hasFile('cover')) {
            if ($album?->cover) Storage::disk('public')->delete($album->cover);
            $d['cover'] = $request->file('cover')->store('covers', 'public');
        }
        return $d;
    }

    public function store(Request $request)
    {
        Album::create($this->data($request));
        return redirect()->route('admin.albums.index')->with('ok', 'Album ditambahkan.');
    }

    public function update(Request $request, Album $album)
    {
        $album->update($this->data($request, $album));
        return redirect()->route('admin.albums.index')->with('ok', 'Album diperbarui.');
    }

    public function destroy(Album $album)
    {
        if ($album->cover) Storage::disk('public')->delete($album->cover);
        $album->delete(); // lagu tetap ada (album_id jadi null)
        return back()->with('ok', 'Album dihapus.');
    }
}
