<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\StudioRequestUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\{Song, StudioRequest};
use App\Services\{AudioTrainingService, CatalogSyncService, LrsService};
use App\Support\SafeBroadcast;
use Illuminate\Http\{JsonResponse, Request};

/**
 * Endpoint Role USER (/api/v1/studio/*):
 * Terisolasi dari endpoint AI/API Admin, tidak memaparkan secret/key/model AI,
 * namun tersinkron secara real-time dengan Admin Studio melalui event & catalog version.
 */
class StudioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $songs = Song::with(['artist:id,name', 'genre:id,name', 'recordLabel:id,name,slug'])
            ->latest()->get()
            ->map(fn (Song $s) => [
                'id'               => $s->id,
                'title'            => $s->title,
                'artist'           => $s->artist?->name,
                'genre'            => $s->genre?->name,
                'label'            => $s->recordLabel?->name,
                'media_format'     => $s->media_format ?: 'mp3',
                'duration_label'   => $s->duration_label,
                'is_smoothed'      => (bool) $s->is_voice_smoothed,
                'sound_style'      => $s->ai_mood ?: 'Standar',
                'clarity_score'    => $s->voice_clarity_score,
                'smoothness_score' => $s->voice_smoothness_score,
                'sound_notes'      => $s->ai_mastering_notes,
                'eq_bands'         => $s->ai_eq_bands ?? [0,0,0,0,0,0,0,0,0,0],
                'stream_url'       => route('songs.stream', $s),
            ]);

        $myRequests = StudioRequest::with('song:id,title')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(15)
            ->get();

        $styles = collect(AudioTrainingService::VOICE_PRESETS)->map(fn ($v, $k) => [
            'key'     => $k,
            'label'   => $v['label'],
            'summary' => $v['summary'],
        ])->values();

        return response()->json([
            'feature'         => 'studio_musik_user',
            'catalog_version' => CatalogSyncService::version(),
            'preferences'     => $user->preferences ?? [],
            'styles'          => $styles,
            'songs'           => $songs,
            'my_requests'     => $myRequests,
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'eq_preset'            => 'sometimes|string|in:normal,bass,vocal,lofi,rock,acoustic,electronic,custom,auto_genre,adaptive',
            'eq_mode'              => 'sometimes|string|in:manual,auto_genre,adaptive',
            'eq_direction'         => 'sometimes|string|in:balanced,warm_bass,bright_clear,punchy,focus_study',
            'eq_intensity'         => 'sometimes|integer|min:0|max:100',
            'eq_auto_gain'         => 'sometimes|boolean',
            'eq_macro'             => 'sometimes|array',
            'eq_macro.bass_tilt'   => 'sometimes|numeric|min:-6|max:6',
            'eq_macro.vocal_focus' => 'sometimes|numeric|min:-6|max:6',
            'eq_macro.treble_air'  => 'sometimes|numeric|min:-6|max:6',
            'eq_bands'             => 'sometimes|array|size:10',
            'eq_bands.*'           => 'numeric|min:-12|max:12',
            'speed'                => 'sometimes|numeric|min:0.5|max:2.0',
        ]);

        $user  = $request->user();
        $prefs = array_merge($user->preferences ?? [], $data);
        $user->update(['preferences' => $prefs]);

        return response()->json([
            'message'     => 'Pengaturan Studio Musik disimpan.',
            'preferences' => $prefs,
        ]);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'song_id'       => 'required|exists:songs,id',
            'desired_style' => 'required|string|in:halus_jernih,vokal_lembut,hangat_alami,bersih_seimbang,nyaman_fokus',
            'note'          => 'nullable|string|max:400',
        ]);

        $song = Song::findOrFail($data['song_id']);
        $req  = StudioRequest::create([
            'user_id'       => $request->user()->id,
            'song_id'       => $song->id,
            'desired_style' => $data['desired_style'],
            'note'          => $data['note'] ?? null,
            'status'        => 'pending',
        ]);

        LrsService::record('requested_studio', 'Song', $song->id, $song->title, ['style' => $data['desired_style'], 'channel' => 'api']);
        SafeBroadcast::dispatch(new StudioRequestUpdatedEvent($req));
        CatalogSyncService::bump('studio_request', 'studio', $song->id, $song->title);

        return response()->json([
            'message' => 'Permintaan Studio Musik terkirim secara real-time.',
            'request' => $req->load('song:id,title'),
        ], 201);
    }
}
