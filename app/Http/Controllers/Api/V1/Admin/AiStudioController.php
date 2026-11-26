<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\{CatalogUpdatedEvent, SongFinetunedEvent};
use App\Http\Controllers\Controller;
use App\Models\{AiTrainingRun, ApiEndpointRule, AudioTrainingSample, AuditLog, Song};
use App\Services\{AiMusicService, AudioTrainingService};
use App\Support\SafeBroadcast;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Cache;

class AiStudioController extends Controller
{
    public function status(AiMusicService $ai): JsonResponse
    {
        $setting = $ai::currentSetting();

        return response()->json([
            'provider'               => $setting->provider,
            'model'                  => $setting->model,
            'base_url'               => $setting->base_url,
            'has_api_key'            => $setting->hasApiKey(),
            'key_hint'               => $setting->key_hint,
            'auto_finetune_realtime' => $setting->auto_finetune_realtime,
            'is_active'              => $setting->is_active,
            'last_tested_at'         => $setting->last_tested_at?->toIso8601String(),
            'last_status'            => $setting->last_status,
            'api_rules'              => ApiEndpointRule::orderBy('group_name')->get(),
            'training_summary'       => [
                'samples_total'      => AudioTrainingSample::count(),
                'train_samples'      => AudioTrainingSample::where('dataset_split', 'train')->count(),
                'validation_samples' => AudioTrainingSample::where('dataset_split', 'validation')->count(),
                'smoothed_songs'     => Song::where('is_voice_smoothed', true)->count(),
                'latest_run'         => AiTrainingRun::latest()->first(),
            ],
        ]);
    }

    public function saveProvider(Request $request, AiMusicService $ai): JsonResponse
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
            'auto_finetune_realtime' => $request->boolean('auto_finetune_realtime', $setting->auto_finetune_realtime),
            'is_active'              => $request->boolean('is_active', $setting->is_active),
            'updated_by'             => auth()->id(),
        ]);

        if (! empty($data['api_key'])) {
            $setting->setPlainApiKey($data['api_key']);
        }
        $setting->save();

        AuditLog::record('ai_config_updated', 'AiProviderSetting', $setting->id);

        return response()->json([
            'message'  => 'Pengaturan kunci AI berhasil disimpan.',
            'has_key'  => $setting->hasApiKey(),
            'key_hint' => $setting->key_hint,
        ]);
    }

    public function autoLabel(AudioTrainingService $trainer): JsonResponse
    {
        $count = $trainer->autoLabelAllSongs(auth()->id(), false);

        return response()->json([
            'message' => "{$count} lagu selesai diberi label & dibagi ke data latih/validasi.",
            'count'   => $count,
        ]);
    }

    public function labelSong(Request $request, Song $song, AudioTrainingService $trainer): JsonResponse
    {
        $data = $request->validate([
            'preset_key'         => 'required|string|in:halus_jernih,vokal_lembut,hangat_alami,bersih_seimbang,nyaman_fokus',
            'dataset_split'      => 'required|in:train,validation',
            'target_clarity'     => 'sometimes|integer|min:50|max:100',
            'target_smoothness'  => 'sometimes|integer|min:50|max:100',
            'noise_reduction_db' => 'sometimes|numeric|min:0|max:20',
            'notes'              => 'nullable|string|max:300',
        ]);

        $sample = $trainer->labelSong($song, $data, auth()->id());

        return response()->json([
            'message' => 'Label lagu berhasil disimpan.',
            'sample'  => $sample,
        ]);
    }

    public function trainModel(Request $request, AudioTrainingService $trainer): JsonResponse
    {
        $data = $request->validate([
            'epochs'     => 'sometimes|integer|min:20|max:500',
            'model_name' => 'sometimes|string|max:100',
        ]);

        $run = $trainer->trainModel(
            (int) ($data['epochs'] ?? 120),
            auth()->id(),
            $data['model_name'] ?? 'Model Penjernih Suara Musikku'
        );

        return response()->json([
            'message' => 'Pelatihan model selesai.',
            'run'     => $run,
        ]);
    }

    public function validateModel(AudioTrainingService $trainer): JsonResponse
    {
        $run = $trainer->validateModel(null, auth()->id());

        return response()->json([
            'message' => 'Uji validasi model selesai.',
            'run'     => $run,
        ]);
    }

    public function polishSong(Request $request, Song $song, AudioTrainingService $trainer): JsonResponse
    {
        $data = $request->validate([
            'preset_key'     => 'nullable|string|in:halus_jernih,vokal_lembut,hangat_alami,bersih_seimbang,nyaman_fokus',
            'noise_strength' => 'nullable|in:light,medium,strong',
            'custom_prompt'  => 'nullable|string|max:400',
            'render_audio'   => 'nullable|boolean',
        ]);

        $res = $trainer->polishSongVoice(
            $song,
            $data['preset_key'] ?? 'halus_jernih',
            $data['noise_strength'] ?? 'medium',
            (string) ($data['custom_prompt'] ?? ''),
            $request->boolean('render_audio', true),
            auth()->id()
        );

        Cache::forget('home:stats');
        SafeBroadcast::dispatch(new SongFinetunedEvent($res['song'], $res['source']));
        SafeBroadcast::dispatch(new CatalogUpdatedEvent('finetuned', 'song', $song->id, $song->title));

        return response()->json([
            'message'           => 'Suara lagu berhasil dilatih agar lebih halus dan jernih.',
            'source'            => $res['source'],
            'rendered_audio'    => $res['rendered_audio'],
            'before_clarity'    => $res['before_clarity'],
            'after_clarity'     => $res['after_clarity'],
            'before_smoothness' => $res['before_smoothness'],
            'after_smoothness'  => $res['after_smoothness'],
            'song'              => $res['song'],
        ]);
    }

    public function finetuneSong(Request $request, Song $song, AiMusicService $ai): JsonResponse
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

        SafeBroadcast::dispatch(new SongFinetunedEvent($res['song'], $res['source']));
        SafeBroadcast::dispatch(new CatalogUpdatedEvent('finetuned', 'song', $song->id, $song->title));

        return response()->json([
            'message' => 'Penyesuaian suara lagu selesai & langsung diperbarui.',
            'source'  => $res['source'],
            'warning' => $res['warning'],
            'song'    => $res['song'],
        ]);
    }

    public function updateRule(Request $request, ApiEndpointRule $rule): JsonResponse
    {
        $data = $request->validate([
            'rate_limit_per_min' => 'sometimes|integer|min:1|max:5000',
            'is_enabled'         => 'sometimes|boolean',
            'realtime_broadcast' => 'sometimes|boolean',
        ]);

        $rule->update($data);
        Cache::forget("api_rule:{$rule->endpoint_key}");

        return response()->json(['message' => 'Aturan koneksi diperbarui.', 'rule' => $rule->fresh()]);
    }
}
