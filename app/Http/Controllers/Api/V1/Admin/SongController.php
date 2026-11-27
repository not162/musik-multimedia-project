<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\SongFinetunedEvent;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Song};
use App\Services\{AiMusicService, AudioTrainingService, CatalogSyncService};
use App\Support\SafeBroadcast;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Storage;

class SongController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $songs = Song::with(['artist:id,name', 'genre:id,name', 'recordLabel:id,name,slug'])
            ->search($request->query('q'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return response()->json($songs);
    }

    public function show(Song $song): JsonResponse
    {
        return response()->json(['song' => $song->load(['artist', 'album', 'genre', 'recordLabel'])]);
    }

    public function store(Request $request, AiMusicService $ai, AudioTrainingService $trainer): JsonResponse
    {
        $data = $this->validated($request);
        $song = Song::create($data);

        $meta = $ai->probeAudio($song);
        if ((int) $song->duration <= 0 && ! empty($meta['duration_s'])) {
            $song->update(['duration' => $meta['duration_s']]);
        }

        $targetKhz = $request->filled('target_sample_rate_khz') ? (int) $request->input('target_sample_rate_khz') : null;
        if ($ai::currentSetting()->auto_finetune_realtime || $targetKhz !== null) {
            $res = $trainer->polishSongVoice($song->fresh(), 'halus_jernih', 'medium', '', true, auth()->id(), $targetKhz);
            SafeBroadcast::dispatch(new SongFinetunedEvent($res['song'], $res['source']));
        }

        AuditLog::record('create', 'Song', $song->id, newValues: $data, description: "Created song: {$song->title}");
        $this->bustAndNotify('created', $song->fresh());

        return response()->json(['message' => 'Lagu diunggah & diperbarui otomatis ke publik dan user.', 'song' => $song->fresh(['artist', 'recordLabel'])], 201);
    }

    public function update(Request $request, Song $song, AiMusicService $ai, AudioTrainingService $trainer): JsonResponse
    {
        $old  = $song->toArray();
        $data = $this->validated($request, $song);
        $song->update($data);

        $targetKhz = $request->filled('target_sample_rate_khz') ? (int) $request->input('target_sample_rate_khz') : null;
        if ($request->hasFile('audio') || $targetKhz !== null) {
            $res = $trainer->polishSongVoice($song->fresh(), $song->voice_preset ?: 'halus_jernih', 'medium', '', true, auth()->id(), $targetKhz);
            SafeBroadcast::dispatch(new SongFinetunedEvent($res['song'], $res['source']));
        }

        AuditLog::record('update', 'Song', $song->id, $old, $data, "Updated song: {$song->title}");
        $this->bustAndNotify('updated', $song->fresh());

        return response()->json(['message' => 'Lagu diperbarui & tersinkron real-time.', 'song' => $song->fresh()]);
    }

    public function destroy(Song $song): JsonResponse
    {
        Storage::disk('public')->delete(array_filter([$song->file_path, $song->cover, $song->hires_file_path]));
        AuditLog::record('delete', 'Song', $song->id, $song->toArray(), description: "Deleted song: {$song->title}");

        $id = $song->id;
        $title = $song->title;
        $song->delete();

        CatalogSyncService::bump('deleted', 'song', $id, $title);

        return response()->json(['message' => 'Lagu dihapus.']);
    }

    private function validated(Request $request, ?Song $song = null): array
    {
        $data = $request->validate([
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

        unset($data['audio'], $data['cover']);
        $data['duration'] = (int) ($data['duration'] ?? 0);

        if ($request->hasFile('audio')) {
            if ($song && $song->file_path) {
                Storage::disk('public')->delete($song->file_path);
            }
            $file = $request->file('audio');
            $data['file_path']    = $file->store('songs', 'public');
            $data['media_format'] = strtolower($file->getClientOriginalExtension() ?: 'mp3');
        }

        if ($request->hasFile('cover')) {
            if ($song?->cover) {
                Storage::disk('public')->delete($song->cover);
            }
            $data['cover'] = $request->file('cover')->store('covers', 'public');
        }

        return $data;
    }

    private function bustAndNotify(string $action, Song $song): void
    {
        CatalogSyncService::bump($action, 'song', $song->id, $song->title, [
            'format'        => $song->media_format,
            'frequency_khz' => $song->target_sample_rate_khz,
        ]);
    }
}
