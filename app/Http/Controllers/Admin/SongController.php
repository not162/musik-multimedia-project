<?php
namespace App\Http\Controllers\Admin;

use App\Events\SongFinetunedEvent;
use App\Http\Controllers\Controller;
use App\Models\{Album, Artist, AuditLog, Genre, RecordLabel, Song};
use App\Services\{AiMusicService, AudioTrainingService, CatalogSyncService};
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'title'                  => 'required|string|max:150',
            'artist_id'              => 'required|exists:artists,id',
            'album_id'               => 'nullable|exists:albums,id',
            'genre_id'               => 'nullable|exists:genres,id',
            'record_label_id'        => 'nullable|exists:record_labels,id',
            'duration'               => 'nullable|integer|min:0|max:36000',
            'lyrics'                 => 'nullable|string|max:20000',
            'target_sample_rate_khz' => 'nullable|integer|min:100|max:1100',
            'audio'                  => ($song ? 'nullable' : 'required') . '|file|mimes:mp3,mp4,wav,ogg,m4a,aac,flac|max:102400',
            'cover'                  => 'nullable|image|max:4096',
        ]);
        unset($d['audio'], $d['cover']);
        $d['duration'] = (int) ($d['duration'] ?? 0);

        if ($request->hasFile('audio')) {
            if ($song && $song->file_path) {
                Storage::disk('public')->delete($song->file_path);
            }
            $file = $request->file('audio');
            $d['file_path']    = $file->store('songs', 'public');
            $d['media_format'] = strtolower($file->getClientOriginalExtension() ?: 'mp3');
        }
        if ($request->hasFile('cover')) {
            if ($song?->cover) {
                Storage::disk('public')->delete($song->cover);
            }
            $d['cover'] = $request->file('cover')->store('covers', 'public');
        }
        return $d;
    }

    public function store(Request $request, AiMusicService $ai, AudioTrainingService $trainer)
    {
        $song = Song::create($this->data($request));
        $meta = $ai->probeAudio($song);
        if ((int) $song->duration <= 0 && ! empty($meta['duration_s'])) {
            $song->update(['duration' => $meta['duration_s']]);
        }

        AuditLog::record('create', 'Song', $song->id, newValues: $song->only(['title', 'artist_id', 'record_label_id', 'media_format', 'target_sample_rate_khz']));

        $targetKhz = $request->filled('target_sample_rate_khz') ? (int) $request->input('target_sample_rate_khz') : null;
        if ($ai::currentSetting()->auto_finetune_realtime || $request->boolean('polish_voice') || $targetKhz !== null) {
            $res = $trainer->polishSongVoice($song->fresh(), 'halus_jernih', 'medium', '', true, auth()->id(), $targetKhz);
            SafeBroadcast::dispatch(new SongFinetunedEvent($res['song'], $res['source']));
        }

        $this->bustAndNotify('created', $song->fresh());
        return redirect()->route('admin.songs.index')->with('ok', 'Lagu baru (' . strtoupper($song->fresh()->media_format ?? 'mp3') . ') berhasil ditambahkan & langsung tampil di halaman publik serta pengguna!');
    }

    public function update(Request $request, Song $song, AiMusicService $ai, AudioTrainingService $trainer)
    {
        $old = $song->only(['title', 'artist_id', 'album_id', 'genre_id', 'record_label_id', 'target_sample_rate_khz']);
        $song->update($this->data($request, $song));
        $meta = $ai->probeAudio($song->fresh());
        if ((int) $song->duration <= 0 && ! empty($meta['duration_s'])) {
            $song->update(['duration' => $meta['duration_s']]);
        }

        $targetKhz = $request->filled('target_sample_rate_khz') ? (int) $request->input('target_sample_rate_khz') : null;
        if ($request->hasFile('audio') || $request->boolean('polish_voice') || ($targetKhz !== null && $targetKhz !== $old['target_sample_rate_khz'])) {
            $res = $trainer->polishSongVoice($song->fresh(), $song->voice_preset ?: 'halus_jernih', 'medium', '', true, auth()->id(), $targetKhz);
            SafeBroadcast::dispatch(new SongFinetunedEvent($res['song'], $res['source']));
        }

        AuditLog::record('update', 'Song', $song->id, $old, $song->fresh()->only(['title', 'artist_id', 'album_id', 'genre_id', 'record_label_id', 'target_sample_rate_khz']));
        $this->bustAndNotify('updated', $song->fresh());
        return redirect()->route('admin.songs.index')->with('ok', 'Lagu diperbarui & disinkronkan ke halaman publik dan pengguna.');
    }

    public function destroy(Song $song)
    {
        Storage::disk('public')->delete(array_filter([$song->file_path, $song->cover, $song->hires_file_path]));
        AuditLog::record('delete', 'Song', $song->id, $song->only(['title', 'file_path']));
        $id    = $song->id;
        $title = $song->title;
        $song->delete();

        CatalogSyncService::bump('deleted', 'song', $id, $title);
        return back()->with('ok', 'Lagu dihapus & daftar lagu publik diperbarui.');
    }

    private function bustAndNotify(string $action, Song $song): void
    {
        CatalogSyncService::bump($action, 'song', $song->id, $song->title, [
            'format'        => $song->media_format,
            'frequency_khz' => $song->target_sample_rate_khz,
        ]);
    }
}
