<?php

namespace App\Services;

use App\Models\{AiProviderSetting, Song};
use Illuminate\Support\Facades\{Http, Log};

class AiMusicService
{
    private const FREQS = [32, 64, 125, 250, 500, 1000, 2000, 4000, 8000, 16000];

    public static function currentSetting(): AiProviderSetting
    {
        return AiProviderSetting::firstOrCreate([], [
            'provider'               => 'gemini',
            'model'                  => 'gemini-2.5-flash',
            'auto_finetune_realtime' => true,
            'is_active'              => true,
        ]);
    }

    /**
     * Test connectivity to the configured AI API using the encrypted API key.
     */
    public function testConnection(AiProviderSetting $setting): array
    {
        $apiKey = $setting->getDecryptedApiKey();
        if (! $apiKey) {
            return ['ok' => false, 'message' => 'API AI Key belum diisi.'];
        }

        try {
            $reply = $this->callAiApi($setting, $apiKey, 'Balas hanya JSON: {"status":"ok","engine":"ready"}');
            $setting->update([
                'last_tested_at' => now(),
                'last_status'    => 'Terhubung (' . $setting->provider . ' / ' . $setting->model . ')',
            ]);
            return ['ok' => true, 'message' => 'Koneksi API AI berhasil!', 'raw' => $reply];
        } catch (\Throwable $e) {
            $setting->update([
                'last_tested_at' => now(),
                'last_status'    => 'Gagal: ' . substr($e->getMessage(), 0, 140),
            ]);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Analyze and fine-tune a song via AI API (with encrypted key) or intelligent DSP fallback.
     */
    public function finetuneSong(Song $song, string $targetPrompt = '', ?string $directionHint = null): array
    {
        $song->loadMissing(['artist', 'album', 'genre', 'recordLabel']);
        $audioMeta = $this->probeAudio($song);
        $setting   = self::currentSetting();
        $apiKey    = $setting->is_active ? $setting->getDecryptedApiKey() : null;

        $usedSource = 'dsp_local';
        $warning    = null;
        $parsed     = null;

        if ($apiKey) {
            try {
                $prompt = $this->buildPrompt($song, $audioMeta, $targetPrompt, $directionHint);
                $raw    = $this->callAiApi($setting, $apiKey, $prompt);
                $parsed = $this->extractJson($raw);
                if ($parsed) {
                    $usedSource = 'ai_api';
                }
            } catch (\Throwable $e) {
                $warning = 'AI API fallback ke DSP lokal: ' . $e->getMessage();
                Log::warning('AI Finetune fallback', ['song_id' => $song->id, 'error' => $e->getMessage()]);
            }
        } else {
            $warning = 'API AI Key belum dikonfigurasi — menggunakan profil DSP cerdas lokal.';
        }

        $profile = $this->normalizeProfile($parsed ?? $this->localHeuristic($song, $directionHint), $directionHint);

        $song->update([
            'is_ai_finetuned'    => true,
            'ai_mood'            => $profile['mood'],
            'ai_tempo_bpm'       => $profile['bpm'],
            'ai_direction'       => $profile['direction'],
            'ai_eq_bands'        => $profile['eq_bands'],
            'ai_preamp_db'       => $profile['preamp_db'],
            'ai_pitch_rate'      => $profile['pitch_rate'],
            'ai_mastering_notes' => $profile['mastering_notes'],
            'ai_finetuned_at'    => now(),
            'audio_meta'         => $audioMeta,
        ]);

        return [
            'song'    => $song->fresh(['artist', 'genre', 'album', 'recordLabel']),
            'source'  => $usedSource,
            'warning' => $warning,
        ];
    }

    /**
     * Probe actual audio stream properties using ffmpeg/ffprobe if available.
     */
    public function probeAudio(Song $song): array
    {
        $path = storage_path('app/public/' . $song->file_path);
        if (! file_exists($path)) {
            return ['exists' => false];
        }

        $meta = [
            'exists'     => true,
            'size_kb'    => round(filesize($path) / 1024, 1),
            'extension'  => pathinfo($path, PATHINFO_EXTENSION),
            'codec'      => strtoupper(pathinfo($path, PATHINFO_EXTENSION)),
            'sample_rate' => 44100,
            'bitrate_kbps' => null,
        ];

        $ffmpeg = $this->findFfmpeg();
        if ($ffmpeg) {
            $cmd = sprintf('"%s" -hide_banner -i %s 2>&1', $ffmpeg, escapeshellarg($path));
            @exec($cmd, $lines);
            $out = implode("\n", $lines);
            if (preg_match('/(\d+)\s*Hz/', $out, $m)) $meta['sample_rate'] = (int) $m[1];
            if (preg_match('/bitrate:\s*(\d+)\s*kb\/s/', $out, $m)) $meta['bitrate_kbps'] = (int) $m[1];
            if (preg_match('/Audio:\s*([a-zA-Z0-9_]+)/', $out, $m)) $meta['codec'] = strtoupper($m[1]);
        }

        return $meta;
    }

    /**
     * Render real 10-band EQ + EBU R128 loudness normalization to the song audio file via FFmpeg.
     */
    public function renderFfmpegMaster(Song $song, array $bands, float $preampDb = 0.0, bool $normalize = true): array
    {
        $ffmpeg = $this->findFfmpeg();
        if (! $ffmpeg) {
            return ['ok' => false, 'message' => 'FFmpeg tidak ditemukan di sistem.'];
        }

        $src = storage_path('app/public/' . $song->file_path);
        if (! file_exists($src)) {
            return ['ok' => false, 'message' => 'File audio tidak ditemukan.'];
        }

        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION)) ?: 'mp3';
        $tmp = storage_path('app/public/songs/master_' . $song->id . '_' . time() . '.' . $ext);

        $filters = [];
        if (abs($preampDb) > 0.05) {
            $filters[] = sprintf('volume=%.2fdB', $preampDb);
        }
        foreach (self::FREQS as $idx => $hz) {
            $gain = round((float) ($bands[$idx] ?? 0), 2);
            if (abs($gain) >= 0.2) {
                $filters[] = sprintf('equalizer=f=%d:t=q:w=1.414:g=%.2f', $hz, $gain);
            }
        }
        if ($normalize) {
            $filters[] = 'loudnorm=I=-14:TP=-1.5:LRA=11';
        }

        if (empty($filters)) {
            $filters[] = 'anull';
        }

        $af = implode(',', $filters);
        $cmd = sprintf(
            '"%s" -y -i %s -af %s %s 2>&1',
            $ffmpeg,
            escapeshellarg($src),
            escapeshellarg($af),
            escapeshellarg($tmp)
        );

        @exec($cmd, $output, $code);

        if ($code !== 0 || ! file_exists($tmp) || filesize($tmp) < 1024) {
            @unlink($tmp);
            return ['ok' => false, 'message' => 'Render FFmpeg gagal: ' . implode(' ', array_slice($output, -4))];
        }

        // Replace original file cleanly
        $newRel = 'songs/' . basename($tmp);
        @unlink($src);
        $song->update([
            'file_path'  => $newRel,
            'audio_meta' => $this->probeAudio($song->fresh()),
        ]);

        return ['ok' => true, 'message' => 'Audio berhasil di-mastering via FFmpeg DSP & Loudness Normalization (-14 LUFS).'];
    }

    // ── Internal AI HTTP Clients ─────────────────────────────────────

    private function callAiApi(AiProviderSetting $setting, string $apiKey, string $prompt): string
    {
        if ($setting->provider === 'gemini') {
            $model = $setting->model ?: 'gemini-2.5-flash';
            $url   = ($setting->base_url ?: 'https://generativelanguage.googleapis.com/v1beta')
                . "/models/{$model}:generateContent?key={$apiKey}";

            $res = Http::timeout(25)->post($url, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature'      => 0.4,
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if (! $res->successful()) {
                throw new \RuntimeException('Gemini API HTTP ' . $res->status() . ': ' . substr($res->body(), 0, 180));
            }

            return (string) data_get($res->json(), 'candidates.0.content.parts.0.text', '');
        }

        // OpenAI or OpenAI-compatible custom endpoint
        $endpoint = $setting->base_url
            ? rtrim($setting->base_url, '/') . '/chat/completions'
            : 'https://api.openai.com/v1/chat/completions';

        $res = Http::timeout(25)
            ->withToken($apiKey)
            ->post($endpoint, [
                'model'       => $setting->model ?: 'gpt-4o-mini',
                'temperature' => 0.4,
                'messages'    => [
                    ['role' => 'system', 'content' => 'You are an expert audio mastering engineer. Respond with valid JSON only.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $res->successful()) {
            throw new \RuntimeException('AI API HTTP ' . $res->status() . ': ' . substr($res->body(), 0, 180));
        }

        return (string) data_get($res->json(), 'choices.0.message.content', '');
    }

    private function buildPrompt(Song $song, array $meta, string $targetPrompt, ?string $dirHint): string
    {
        $info = [
            'title'       => $song->title,
            'artist'      => $song->artist?->name,
            'genre'       => $song->genre?->name,
            'album'       => $song->album?->title,
            'label'       => $song->recordLabel?->name,
            'duration_s'  => $song->duration,
            'sample_rate' => $meta['sample_rate'] ?? 44100,
            'bitrate'     => $meta['bitrate_kbps'] ?? null,
            'lyrics_hint' => substr((string) $song->lyrics, 0, 300),
            'admin_goal'  => $targetPrompt ?: 'Optimalkan kejelasan vokal, keseimbangan bass dan treble agar nyaman didengar.',
            'direction_hint' => $dirHint ?: 'balanced',
        ];

        return "Analisa lagu berikut dan hasilkan preset fine-tuning audio 10-band (32,64,125,250,500,1k,2k,4k,8k,16k Hz) dalam JSON ketat dengan kunci: "
            . '{"eq_bands":[10 angka -12 sampai 12],"direction":"balanced|warm_bass|bright_clear|punchy|focus_study","mood":"string pendek bahasa Indonesia","bpm":angka 60-180,"preamp_db":angka -6 sampai 3,"pitch_rate":angka 0.90 sampai 1.10,"mastering_notes":"ringkasan teknis singkat bahasa Indonesia"}'
            . "\nData lagu:\n" . json_encode($info, JSON_UNESCAPED_UNICODE);
    }

    private function extractJson(string $text): ?array
    {
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) return $decoded;
        }
        return null;
    }

    private function localHeuristic(Song $song, ?string $directionHint): array
    {
        $genre = strtolower((string) $song->genre?->name);
        $dir   = $directionHint ?: match (true) {
            str_contains($genre, 'lofi') || str_contains($genre, 'ambient') => 'focus_study',
            str_contains($genre, 'elektronik') || str_contains($genre, 'pop') => 'punchy',
            str_contains($genre, 'akustik') => 'bright_clear',
            default => 'balanced',
        };

        $curves = [
            'balanced'     => [1.0, 0.5, 0.0, -0.5, 0.0, 0.5, 1.0, 1.0, 0.5, 1.0],
            'warm_bass'    => [4.5, 3.5, 2.0, 1.0, 0.0, -0.5, -1.0, -1.0, -0.5, 0.0],
            'bright_clear' => [-1.0, -0.5, 0.0, 0.5, 1.5, 2.5, 3.5, 3.5, 3.0, 2.5],
            'punchy'       => [5.0, 4.0, 1.5, -1.0, -1.5, 0.5, 1.5, 3.0, 3.5, 4.0],
            'focus_study'  => [-0.5, 0.5, 1.5, 1.0, 1.5, 1.0, -1.0, -2.0, -1.5, -1.0],
        ];

        $moods = [
            'focus_study'  => 'Fokus Tenang',
            'punchy'       => 'Energik Dinamis',
            'bright_clear' => 'Akustik Jernih',
            'warm_bass'    => 'Hangat & Dalam',
            'balanced'     => 'Seimbang Studio',
        ];

        $bands = $curves[$dir] ?? $curves['balanced'];
        $max   = max(0, ...$bands);

        return [
            'eq_bands'        => $bands,
            'direction'       => $dir,
            'mood'            => $moods[$dir] ?? 'Seimbang',
            'bpm'             => str_contains($genre, 'elektronik') ? 124 : (str_contains($genre, 'lofi') ? 82 : 104),
            'preamp_db'       => round(-$max * 0.4, 2),
            'pitch_rate'      => 1.0,
            'mastering_notes' => "Kurva DSP {$dir} disesuaikan untuk karakter {$song->genre?->name} dengan proteksi headroom anti-clipping.",
        ];
    }

    private function normalizeProfile(array $raw, ?string $dirHint): array
    {
        $bands = array_slice(array_pad((array) ($raw['eq_bands'] ?? []), 10, 0), 0, 10);
        $bands = array_map(fn ($v) => round(max(-12, min(12, (float) $v)), 1), $bands);

        $validDirs = ['balanced', 'warm_bass', 'bright_clear', 'punchy', 'focus_study'];
        $dir = in_array($raw['direction'] ?? '', $validDirs, true)
            ? $raw['direction']
            : (in_array($dirHint, $validDirs, true) ? $dirHint : 'balanced');

        return [
            'eq_bands'        => $bands,
            'direction'       => $dir,
            'mood'            => substr((string) ($raw['mood'] ?? 'Seimbang'), 0, 60),
            'bpm'             => max(50, min(200, (int) ($raw['bpm'] ?? 100))),
            'preamp_db'       => round(max(-6, min(3, (float) ($raw['preamp_db'] ?? 0))), 2),
            'pitch_rate'      => round(max(0.85, min(1.15, (float) ($raw['pitch_rate'] ?? 1.0))), 2),
            'mastering_notes' => substr((string) ($raw['mastering_notes'] ?? 'Fine-tuning selesai.'), 0, 1000),
        ];
    }

    private function findFfmpeg(): ?string
    {
        foreach ([
            'C:\\ProgramData\\chocolatey\\bin\\ffmpeg.exe',
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
        ] as $cand) {
            if (file_exists($cand)) return $cand;
        }
        $where = trim((string) @shell_exec('where ffmpeg 2>NUL'));
        return $where !== '' ? explode("\n", $where)[0] : null;
    }
}
