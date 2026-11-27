<?php

namespace App\Http\Controllers\Admin;

use App\Events\SongFinetunedEvent;
use App\Http\Controllers\Controller;
use App\Models\{AiTrainingRun, ApiEndpointRule, AudioTrainingSample, AuditLog, RecordLabel, Song};
use App\Services\{AiMusicService, AudioTrainingService, CatalogSyncService};
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

        $samples   = AudioTrainingSample::with('song.artist:id,name')->latest('updated_at')->get();
        $runs      = AiTrainingRun::latest()->limit(6)->get();
        $latestRun = $runs->first();
        $presets   = AudioTrainingService::VOICE_PRESETS;

        $trainStats = [
            'labeled'    => $samples->count(),
            'train'      => $samples->where('dataset_split', 'train')->count(),
            'validation' => $samples->where('dataset_split', 'validation')->count(),
            'smoothed'   => $songs->where('is_voice_smoothed', true)->count(),
            'avg_clarity' => (int) round($songs->whereNotNull('voice_clarity_score')->avg('voice_clarity_score') ?: 0),
        ];

        return view('admin.ai-studio', compact(
            'setting', 'songs', 'labels', 'rules', 'tokens',
            'samples', 'runs', 'latestRun', 'presets', 'trainStats'
        ));
    }

    public function saveProvider(Request $request, AiMusicService $ai)
    {
        $data = $request->validate([
            'provider'               => 'required|in:gemini,openai,nvidia,custom',
            'model'                  => 'required|string|max:100',
            'base_url'               => 'nullable|url|max:255',
            'compute_name'           => 'nullable|string|max:120',
            'compute_key'            => 'nullable|string|max:300',
            'api_key'                => 'nullable|string|max:300',
            'auto_finetune_realtime' => 'boolean',
            'is_active'              => 'boolean',
        ]);

        $setting = $ai::currentSetting();
        $setting->fill([
            'provider'               => $data['provider'],
            'model'                  => $data['model'],
            'base_url'               => $data['base_url'] ?? ($data['provider'] === 'nvidia' ? 'https://integrate.api.nvidia.com/v1' : null),
            'compute_name'           => $data['compute_name'] ?? $setting->compute_name,
            'auto_finetune_realtime' => $request->boolean('auto_finetune_realtime'),
            'is_active'              => $request->boolean('is_active'),
            'updated_by'             => auth()->id(),
        ]);

        if (! empty($data['api_key'])) {
            $setting->setPlainApiKey($data['api_key']);
        }
        if (! empty($data['compute_key'])) {
            $setting->setPlainComputeKey($data['compute_key']);
        }
        $setting->save();

        AuditLog::record('ai_config_updated', 'AiProviderSetting', $setting->id,
            description: "Pengaturan AI {$setting->provider} ({$setting->model}) disimpan dengan aman.");

        return back()->with('ok', 'Pengaturan model AI, compute, & kunci akses berhasil disimpan dengan aman.');
    }

    public function testProvider(AiMusicService $ai)
    {
        $res = $ai->testConnection($ai::currentSetting());
        return $res['ok']
            ? back()->with('ok', 'Sambungan ke model AI berhasil dan siap digunakan!')
            : back()->withErrors(['ai' => 'Sambungan ke model AI belum berhasil: ' . $res['message']]);
    }

    // ── Alat Bantu Latih: Langkah 1 — Labelling ──────────────────────

    public function labelSong(Request $request, Song $song, AudioTrainingService $trainer)
    {
        $data = $request->validate([
            'preset_key'         => 'required|string|in:halus_jernih,vokal_lembut,hangat_alami,bersih_seimbang,nyaman_fokus',
            'dataset_split'      => 'required|in:train,validation',
            'target_clarity'     => 'required|integer|min:50|max:100',
            'target_smoothness'  => 'required|integer|min:50|max:100',
            'noise_reduction_db' => 'nullable|numeric|min:0|max:20',
            'notes'              => 'nullable|string|max:300',
            'use_ai'             => 'nullable|boolean',
        ]);

        $sample = $trainer->labelSong($song, array_merge($data, [
            'use_ai' => $request->boolean('use_ai'),
        ]), auth()->id());

        AuditLog::record('audio_sample_labeled', 'Song', $song->id,
            newValues: ['label' => $sample->label_name, 'split' => $sample->dataset_split],
            description: "Memberi label '{$sample->label_name}' ({$sample->dataset_split}) untuk lagu '{$song->title}'.");

        $splitTxt = $sample->dataset_split === 'validation' ? 'Data Uji (Validation)' : 'Data Latih (Train)';
        return back()->with('ok', "Lagu \"{$song->title}\" berhasil dilabeli \"{$sample->label_name}\" dan dimasukkan ke {$splitTxt}.");
    }

    public function autoLabelAll(AudioTrainingService $trainer)
    {
        $count = $trainer->autoLabelAllSongs(auth()->id(), true);
        AuditLog::record('audio_auto_labeled', 'AudioTrainingSample', null, description: "Memberi label otomatis untuk {$count} lagu ke kelompok Latih & Uji.");

        return back()->with('ok', "{$count} lagu berhasil diberi label otomatis dan dibagi ke Data Latih & Data Uji (Validation).");
    }

    // ── Alat Bantu Latih: Langkah 2 — Train ──────────────────────────

    public function trainModel(Request $request, AudioTrainingService $trainer)
    {
        $data = $request->validate([
            'epochs'     => 'nullable|integer|min:20|max:500',
            'model_name' => 'nullable|string|max:100',
        ]);

        $run = $trainer->trainModel(
            (int) ($data['epochs'] ?? 120),
            auth()->id(),
            $data['model_name'] ?: 'Model Penjernih Suara Musikku'
        );

        AuditLog::record('ai_model_trained', 'AiTrainingRun', $run->id,
            newValues: ['train_count' => $run->train_count, 'epochs' => $run->epochs, 'train_loss' => $run->train_loss],
            description: "Melatih model '{$run->model_name}' dengan {$run->train_count} contoh lagu.");

        return back()->with('ok', "Pelatihan selesai! Model mempelajari {$run->train_count} lagu selama {$run->epochs} putaran (selisih latihan: {$run->train_loss}).");
    }

    // ── Alat Bantu Latih: Langkah 3 — Validation ─────────────────────

    public function validateModel(AudioTrainingService $trainer)
    {
        $run = $trainer->validateModel(null, auth()->id());

        AuditLog::record('ai_model_validated', 'AiTrainingRun', $run->id,
            newValues: ['val_count' => $run->val_count, 'val_accuracy' => $run->val_accuracy, 'clarity_gain_pct' => $run->clarity_gain_pct],
            description: "Validasi model '{$run->model_name}': ketepatan {$run->val_accuracy}%.");

        return back()->with('ok', "Uji validasi selesai pada {$run->val_count} lagu uji! Ketepatan model {$run->val_accuracy}% dengan perkiraan peningkatan kejernihan +{$run->clarity_gain_pct} poin.");
    }

    // ── Alat Bantu Latih: Langkah 4 — Latih Suara Halus & Jernih ────

    public function polishSong(Request $request, Song $song, AudioTrainingService $trainer)
    {
        $data = $request->validate([
            'preset_key'             => 'nullable|string|in:halus_jernih,vokal_lembut,hangat_alami,bersih_seimbang,nyaman_fokus',
            'noise_strength'         => 'nullable|in:light,medium,strong',
            'target_sample_rate_khz' => 'nullable|integer|min:100|max:1100',
            'custom_prompt'          => 'nullable|string|max:400',
            'render_audio'           => 'nullable|boolean',
            'record_label_id'        => 'nullable|exists:record_labels,id',
        ]);

        if (! empty($data['record_label_id'])) {
            $song->update(['record_label_id' => $data['record_label_id']]);
        }

        $targetKhz = $request->filled('target_sample_rate_khz') ? (int) $data['target_sample_rate_khz'] : null;

        $res = $trainer->polishSongVoice(
            $song,
            $data['preset_key'] ?? ($song->voice_preset ?: 'halus_jernih'),
            $data['noise_strength'] ?? 'medium',
            (string) ($data['custom_prompt'] ?? ''),
            $request->boolean('render_audio', true),
            auth()->id(),
            $targetKhz
        );

        AuditLog::record('ai_voice_polished', 'Song', $song->id,
            newValues: [
                'clarity'    => "{$res['before_clarity']}->{$res['after_clarity']}",
                'smoothness' => "{$res['before_smoothness']}->{$res['after_smoothness']}",
                'freq_khz'   => $targetKhz,
                'rendered'   => $res['rendered_audio'],
            ],
            description: "Melatih suara '{$song->title}' agar lebih halus dan jernih.");

        SafeBroadcast::dispatch(new SongFinetunedEvent($res['song'], $res['source']));
        CatalogSyncService::bump('finetuned', 'song', $song->id, $song->title, [
            'format'        => $res['song']->media_format,
            'frequency_khz' => $res['song']->target_sample_rate_khz,
        ]);

        $freqNote = $targetKhz ? " · Arsip master {$targetKhz} kHz siap" : '';
        return back()->with('ok', "Suara \"{$song->title}\" berhasil dilatih menjadi lebih halus & jernih! Kejernihan: {$res['before_clarity']} → {$res['after_clarity']}/100 · Kehalusan: {$res['before_smoothness']} → {$res['after_smoothness']}/100{$freqNote}.");
    }

    public function polishAll(Request $request, AudioTrainingService $trainer)
    {
        $preset    = $request->input('preset_key', 'halus_jernih');
        $targetKhz = $request->filled('target_sample_rate_khz') ? max(100, min(1100, (int) $request->input('target_sample_rate_khz'))) : null;
        $songs     = Song::orderBy('id')->get();
        foreach ($songs as $song) {
            $res = $trainer->polishSongVoice($song, $preset, 'medium', '', true, auth()->id(), $targetKhz);
            SafeBroadcast::dispatch(new SongFinetunedEvent($res['song'], $res['source']));
        }

        if ($songs->first()) {
            CatalogSyncService::bump('finetuned', 'song', $songs->first()->id, 'Seluruh Katalog');
        }
        AuditLog::record('ai_voice_polished_all', 'Song', null, description: "Melatih & menghaluskan {$songs->count()} lagu sekaligus.");

        return back()->with('ok', "Seluruh {$songs->count()} lagu berhasil dilatih agar lebih halus, jernih, dan bebas bising serta langsung diperbarui ke semua pendengar.");
    }

    // ── Penyempurnaan Cepat & Penyesuaian Manual ─────────────────────

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
            description: "Penyesuaian suara AI untuk '{$song->title}'");

        SafeBroadcast::dispatch(new SongFinetunedEvent($res['song'], $res['source']));
        CatalogSyncService::bump('finetuned', 'song', $song->id, $song->title);

        $msg = $res['source'] === 'ai_api'
            ? "Suara lagu \"{$song->title}\" berhasil disesuaikan oleh model AI dan langsung diperbarui ke semua pengguna!"
            : "Suara lagu \"{$song->title}\" disesuaikan dengan profil pintar (" . ($res['warning'] ?? '') . ").";

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

        AuditLog::record('manual_finetune_song', 'Song', $song->id, description: "Penyesuaian suara manual: {$song->title}");
        SafeBroadcast::dispatch(new SongFinetunedEvent($song->fresh(), 'admin_dsp'));
        CatalogSyncService::bump('updated', 'song', $song->id, $song->title);

        return back()->with('ok', "Setelan suara \"{$song->title}\" disimpan & langsung berlaku{$extra}");
    }

    public function updateRule(Request $request, ApiEndpointRule $rule)
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

        return back()->with('ok', "Aturan koneksi '{$rule->endpoint_key}' diperbarui.");
    }
}
