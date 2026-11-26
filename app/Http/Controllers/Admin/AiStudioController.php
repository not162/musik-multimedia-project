<?php

namespace App\Http\Controllers\Admin;

use App\Events\{CatalogUpdatedEvent, SongFinetunedEvent};
use App\Http\Controllers\Controller;
use App\Models\{AiProviderSetting, ApiEndpointRule, AuditLog, RecordLabel, Song};
use App\Services\AiMusicService;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

class AiStudioController extends Controller
{
    public function index(AiMusicService $ai)
    {
        $setting = $ai::currentSetting();
        $songs   = Song::with(['artist:id,name', 'genre:id,name', 'recordLabel:id,name'])
            ->latest()->get();
        $labels  = RecordLabel::orderBy('name')->get();
        $rules   = ApiEndpointRule::orderBy('group_name')->orderBy('endpoint_key')->get();
        $tokens  = PersonalAccessToken::with('tokenable:id,name,email,role')
            ->latest()->limit(20)->get();

        return view('admin.ai-studio', compact('setting', 'songs', 'labels', 'rules', 'tokens'));
    }

    public function saveProvider(Request $request, AiMusicService $ai)
    {
        $data = $request->validate([
            'provider'               => 'required|in:gemini,openai,custom',
            'model'                  => 'required|string|max:100',
            'base_url'               => 'nullable|url|max:255',
            'api_key'                => 'nullable|string|max:300',
            'auto_finetune_realtime' => 'boolean',
            'is_active'              => 'boolean',
        ]);

        $setting = $ai::currentSetting();
        $setting->fill([
            'provider'               => $data['provider'],
            'model'                  => $data['model'],
            'base_url'               => $data['base_url'] ?? null,
            'auto_finetune_realtime' => $request->boolean('auto_finetune_realtime'),
            'is_active'              => $request->boolean('is_active'),
            'updated_by'             => auth()->id(),
        ]);

        if (! empty($data['api_key'])) {
            $setting->setPlainApiKey($data['api_key']);
        }
        $setting->save();

        AuditLog::record('ai_config_updated', 'AiProviderSetting', $setting->id,
            description: "Provider {$setting->provider} ({$setting->model}) disimpan dengan enkripsi AES-256.");

        return back()->with('ok', 'Konfigurasi API AI Key (terenkripsi) berhasil disimpan.');
    }

    public function testProvider(AiMusicService $ai)
    {
        $res = $ai->testConnection($ai::currentSetting());
        return $res['ok']
            ? back()->with('ok', $res['message'])
            : back()->withErrors(['ai' => 'Uji API AI gagal: ' . $res['message']]);
    }

    public function finetuneSong(Request $request, Song $song, AiMusicService $ai)
    {
        $data = $request->validate([
            'target_prompt'   => 'nullable|string|max:400',
            'direction_hint'  => 'nullable|in:balanced,warm_bass,bright_clear,punchy,focus_study',
            'record_label_id' => 'nullable|exists:record_labels,id',
        ]);

        if (array_key_exists('record_label_id', $data)) {
            $song->update(['record_label_id' => $data['record_label_id']]);
        }

        $res = $ai->finetuneSong($song, (string) ($data['target_prompt'] ?? ''), $data['direction_hint'] ?? null);

        AuditLog::record('ai_finetune_song', 'Song', $song->id,
            newValues: ['source' => $res['source'], 'direction' => $res['song']->ai_direction, 'mood' => $res['song']->ai_mood],
            description: "AI Fine-Tuning pada '{$song->title}' ({$res['source']})");

        Cache::forget('home:stats');
        SafeBroadcast::dispatch(new SongFinetunedEvent($res['song'], $res['source']));
        SafeBroadcast::dispatch(new CatalogUpdatedEvent('finetuned', 'song', $song->id, $song->title));

        $msg = $res['source'] === 'ai_api'
            ? "Lagu \"{$song->title}\" berhasil di-finetune dengan AI API & disiarkan real-time!"
            : "Lagu \"{$song->title}\" di-finetune dengan DSP lokal (" . ($res['warning'] ?? '') . ") & disiarkan real-time.";

        return back()->with('ok', $msg);
    }

    public function manualFinetune(Request $request, Song $song, AiMusicService $ai)
    {
        $data = $request->validate([
            'ai_mood'            => 'required|string|max:60',
            'ai_tempo_bpm'       => 'required|integer|min:40|max:240',
            'ai_direction'       => 'required|in:balanced,warm_bass,bright_clear,punchy,focus_study',
            'ai_preamp_db'       => 'required|numeric|min:-6|max:3',
            'ai_pitch_rate'      => 'required|numeric|min:0.85|max:1.15',
            'ai_mastering_notes' => 'nullable|string|max:1000',
            'record_label_id'    => 'nullable|exists:record_labels,id',
            'bands'              => 'required|array|size:10',
            'bands.*'            => 'numeric|min:-12|max:12',
            'render_ffmpeg'      => 'nullable|boolean',
        ]);

        $bands = array_map(fn ($v) => round((float) $v, 1), $data['bands']);

        $song->update([
            'record_label_id'    => $data['record_label_id'] ?? null,
            'is_ai_finetuned'    => true,
            'ai_mood'            => $data['ai_mood'],
            'ai_tempo_bpm'       => $data['ai_tempo_bpm'],
            'ai_direction'       => $data['ai_direction'],
            'ai_eq_bands'        => $bands,
            'ai_preamp_db'       => $data['ai_preamp_db'],
            'ai_pitch_rate'      => $data['ai_pitch_rate'],
            'ai_mastering_notes' => $data['ai_mastering_notes'] ?? '',
            'ai_finetuned_at'    => now(),
        ]);

        $extra = '';
        if ($request->boolean('render_ffmpeg')) {
            $ff = $ai->renderFfmpegMaster($song->fresh(), $bands, (float) $data['ai_preamp_db'], true);
            $extra = ' · ' . $ff['message'];
        }

        AuditLog::record('manual_finetune_song', 'Song', $song->id, description: "Manual DSP Fine-Tuning: {$song->title}");
        SafeBroadcast::dispatch(new SongFinetunedEvent($song->fresh(), 'admin_dsp'));

        return back()->with('ok', "Fine-tuning \"{$song->title}\" disimpan & disiarkan real-time{$extra}");
    }

    public function updateApiRule(Request $request, ApiEndpointRule $rule)
    {
        $data = $request->validate([
            'rate_limit_per_min' => 'required|integer|min:1|max:5000',
            'is_enabled'         => 'boolean',
            'realtime_broadcast' => 'boolean',
        ]);

        $rule->update([
            'rate_limit_per_min' => $data['rate_limit_per_min'],
            'is_enabled'         => $request->boolean('is_enabled'),
            'realtime_broadcast' => $request->boolean('realtime_broadcast'),
        ]);

        Cache::forget("api_rule:{$rule->endpoint_key}");
        AuditLog::record('api_rule_updated', 'ApiEndpointRule', $rule->id, newValues: $rule->only(['is_enabled', 'rate_limit_per_min']));

        return back()->with('ok', "Aturan API '{$rule->endpoint_key}' diperbarui real-time.");
    }
}
