<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArtistController extends Controller
{
    public function index(Request $request)
    {
        $artists = Artist::withCount('songs')
            ->when($request->query('q'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')->paginate(20)->withQueryString();
        return view('admin.artists.index', compact('artists'));
    }

    public function create() { return view('admin.artists.form', ['artist' => new Artist]); }
    public function edit(Artist $artist) { return view('admin.artists.form', compact('artist')); }

    private function data(Request $request, ?Artist $artist = null): array
    {
        $d = $request->validate([
            'name'  => 'required|string|max:120',
            'bio'   => 'nullable|string|max:2000',
            'photo' => 'nullable|image|max:2048',
        ]);
        unset($d['photo']);
        if ($request->hasFile('photo')) {
            if ($artist?->photo) Storage::disk('public')->delete($artist->photo);
            $d['photo'] = $request->file('photo')->store('artists', 'public');
        }
        return $d;
    }

    public function store(Request $request)
    {
        Artist::create($this->data($request));
        return redirect()->route('admin.artists.index')->with('ok', 'Artis ditambahkan.');
    }

    public function update(Request $request, Artist $artist)
    {
        $artist->update($this->data($request, $artist));
        return redirect()->route('admin.artists.index')->with('ok', 'Artis diperbarui.');
    }

    public function destroy(Artist $artist)
    {
        // hapus file audio/cover terkait agar storage tidak menumpuk
        foreach ($artist->songs as $s) {
            Storage::disk('public')->delete(array_filter([$s->file_path, $s->cover]));
        }
        foreach ($artist->albums as $a) if ($a->cover) Storage::disk('public')->delete($a->cover);
        if ($artist->photo) Storage::disk('public')->delete($artist->photo);
        $artist->delete();
        return back()->with('ok', 'Artis dihapus beserta lagu & album-nya.');
    }
}
