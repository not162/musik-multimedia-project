<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Events\{CatalogUpdatedEvent, SongFinetunedEvent};
use App\Http\Controllers\Controller;
use App\Models\{ApiEndpointRule, AuditLog, Song};
use App\Services\AiMusicService;
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
            'message'  => 'Konfigurasi API AI disimpan.',
            'has_key'  => $setting->hasApiKey(),
            'key_hint' => $setting->key_hint,
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
            'message' => 'Fine-tuning lagu selesai & disiarkan real-time.',
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

        return response()->json(['message' => 'Aturan API diperbarui.', 'rule' => $rule->fresh()]);
    }
}
