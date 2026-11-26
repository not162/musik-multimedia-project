<?php
namespace App\Http\Controllers\Admin;

use App\Events\{CatalogUpdatedEvent, SongFinetunedEvent};
use App\Http\Controllers\Controller;
use App\Models\{Album, Artist, AuditLog, Genre, RecordLabel, Song};
use App\Services\AiMusicService;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Storage};

class SongController extends Controller
{
    public function index(Request $request)
    {
        $songs = Song::with(['artist', 'genre', 'recordLabel'])
            ->search($request->query('q'))->latest()->paginate(20)->withQueryString();
        return view('admin.songs.index', compact('songs'));
    }

    private function formData(Song $song): array
    {
        return [
            'song'    => $song,
            'artists' => Artist::orderBy('name')->get(),
            'albums'  => Album::with('artist')->orderBy('title')->get(),
            'genres'  => Genre::orderBy('name')->get(),
            'labels'  => RecordLabel::orderBy('name')->get(),
        ];
    }

    public function create() { return view('admin.songs.form', $this->formData(new Song)); }
    public function edit(Song $song) { return view('admin.songs.form', $this->formData($song)); }

    private function data(Request $request, ?Song $song = null): array
    {
        $d = $request->validate([
            'title'           => 'required|string|max:150',
            'artist_id'       => 'required|exists:artists,id',
            'album_id'        => 'nullable|exists:albums,id',
            'genre_id'        => 'nullable|exists:genres,id',
            'record_label_id' => 'nullable|exists:record_labels,id',
            'duration'        => 'nullable|integer|min:0|max:36000',
            'lyrics'          => 'nullable|string|max:20000',
            'audio'           => ($song ? 'nullable' : 'required') . '|file|mimes:mp3,wav,ogg,m4a,aac,flac|max:30720',
            'cover'           => 'nullable|image|max:2048',
        ]);
        unset($d['audio'], $d['cover']);
        $d['duration'] = (int) ($d['duration'] ?? 0);

        if ($request->hasFile('audio')) {
            if ($song) Storage::disk('public')->delete($song->file_path);
            $d['file_path'] = $request->file('audio')->store('songs', 'public');
        }
        if ($request->hasFile('cover')) {
            if ($song?->cover) Storage::disk('public')->delete($song->cover);
            $d['cover'] = $request->file('cover')->store('covers', 'public');
        }
        return $d;
    }

    public function store(Request $request, AiMusicService $ai)
    {
        $song = Song::create($this->data($request));
        AuditLog::record('create', 'Song', $song->id, newValues: $song->only(['title', 'artist_id', 'record_label_id']));

        // Otomatis fine-tune real-time jika diaktifkan pada AI Studio
        if ($ai::currentSetting()->auto_finetune_realtime) {
            $res = $ai->finetuneSong($song);
            SafeBroadcast::dispatch(new SongFinetunedEvent($res['song'], $res['source']));
        }

        $this->bustAndNotify('created', $song);
        return redirect()->route('admin.songs.index')->with('ok', 'Lagu berhasil diunggah & disinkronkan real-time.');
    }

    public function update(Request $request, Song $song)
    {
        $old = $song->only(['title', 'artist_id', 'album_id', 'genre_id', 'record_label_id']);
        $song->update($this->data($request, $song));
        AuditLog::record('update', 'Song', $song->id, $old, $song->only(['title', 'artist_id', 'album_id', 'genre_id', 'record_label_id']));
        $this->bustAndNotify('updated', $song);
        return redirect()->route('admin.songs.index')->with('ok', 'Lagu diperbarui.');
    }

    public function destroy(Song $song)
    {
        Storage::disk('public')->delete(array_filter([$song->file_path, $song->cover]));
        AuditLog::record('delete', 'Song', $song->id, $song->only(['title', 'file_path']));
        $id = $song->id;
        $title = $song->title;
        $song->delete();
        Cache::forget('home:stats');
        Cache::forget('seo:sitemap:xml');
        SafeBroadcast::dispatch(new CatalogUpdatedEvent('deleted', 'song', $id, $title));
        return back()->with('ok', 'Lagu dihapus.');
    }

    private function bustAndNotify(string $action, Song $song): void
    {
        Cache::forget('home:stats');
        Cache::forget('home:labels');
        Cache::forget('api:charts');
        Cache::forget('seo:sitemap:xml');
        SafeBroadcast::dispatch(new CatalogUpdatedEvent($action, 'song', $song->id, $song->title));
    }
}
