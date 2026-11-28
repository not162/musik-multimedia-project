<?php

namespace App\Http\Controllers;

use App\Events\StudioRequestUpdatedEvent;
use App\Models\{ApiEndpointRule, Genre, Song, StudioRequest};
use App\Services\{AudioTrainingService, CatalogSyncService, LrsService};
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;

/**
 * Fitur Studio Musik khusus Pengguna (User Role) — bebas dari tampilan API & AI Admin,
 * terhubung langsung & real-time dengan pengelola melalui endpoint khusus User.
 */
class StudioController extends Controller
{
    public function index()
    {
        $user  = auth()->user();
        $songs = Song::with(['artist:id,name', 'genre:id,name', 'recordLabel:id,name,slug'])
            ->latest()->get();

        $myRequests = StudioRequest::with('song.artist:id,name')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(15)
            ->get();

        $studioRule     = ApiEndpointRule::where('endpoint_key', 'user.studio')->first();
        $studioEnabled  = $studioRule ? (bool) $studioRule->is_enabled : true;
        $styles         = AudioTrainingService::VOICE_PRESETS;
        $preferences    = $user->preferences ?? [];
        $genres         = Genre::orderBy('name')->get();

        return view('studio.index', compact(
            'user', 'songs', 'myRequests', 'studioEnabled', 'styles', 'preferences', 'genres'
        ));
    }

    public function savePreferences(Request $request)
    {
        $data = $request->validate([
            'eq_mode'              => 'required|in:manual,auto_genre,adaptive',
            'eq_direction'         => 'required|in:balanced,warm_bass,bright_clear,punchy,focus_study',
            'eq_intensity'         => 'required|integer|min:0|max:100',
            'eq_auto_gain'         => 'nullable|boolean',
            'bass_tilt'            => 'nullable|numeric|min:-6|max:6',
            'vocal_focus'          => 'nullable|numeric|min:-6|max:6',
            'treble_air'           => 'nullable|numeric|min:-6|max:6',
            'speed'                => 'nullable|numeric|min:0.5|max:2.0',
        ]);

        $user  = $request->user();
        $prefs = array_merge($user->preferences ?? [], [
            'eq_mode'      => $data['eq_mode'],
            'eq_preset'    => $data['eq_mode'] === 'manual' ? ($user->preferences['eq_preset'] ?? 'custom') : $data['eq_mode'],
            'eq_direction' => $data['eq_direction'],
            'eq_intensity' => (int) $data['eq_intensity'],
            'eq_auto_gain' => $request->boolean('eq_auto_gain', true),
            'speed'        => (float) ($data['speed'] ?? 1.0),
            'eq_macro'     => [
                'bass_tilt'   => (float) ($data['bass_tilt'] ?? 0),
                'vocal_focus' => (float) ($data['vocal_focus'] ?? 0),
                'treble_air'  => (float) ($data['treble_air'] ?? 0),
            ],
        ]);
        $user->update(['preferences' => $prefs]);

        return back()->with('ok', 'Pengaturan Studio Musik Anda berhasil disimpan.');
    }

    public function storeRequest(Request $request)
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

        LrsService::record('requested_studio', 'Song', $song->id, $song->title, ['style' => $data['desired_style']]);
        SafeBroadcast::dispatch(new StudioRequestUpdatedEvent($req));
        CatalogSyncService::bump('studio_request', 'studio', $song->id, $song->title);

        return back()->with('ok', "Permintaan penyesuaian suara untuk \"{$song->title}\" sudah terkirim langsung ke tim pengelola Studio.");
    }

    public function importSong(Request $request, AudioTrainingService $trainer)
    {
        $data = $request->validate(AudioTrainingService::customImportRules());
        $res  = $trainer->importUserCustomSong($request->user(), $request->file('audio'), $data);

        return back()->with('ok', $res['message']);
    }

    public function destroySong(Request $request, Song $song, AudioTrainingService $trainer)
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin() || ($song->uploaded_by !== null && (int) $song->uploaded_by === (int) $user->id),
            403,
            'Anda hanya dapat menghapus lagu custom yang Anda impor sendiri.'
        );

        $title = $song->title;
        $trainer->deleteUserCustomSong($song);

        return back()->with('ok', "Lagu custom \"{$title}\" berhasil dihapus dari Studio Musik.");
    }
}
