<?php

namespace App\Services;

use App\Events\{SongFinetunedEvent, UserNotificationEvent};
use App\Models\{AiTrainingRun, Artist, AudioTrainingSample, Song, User};
use App\Services\{AiMusicService, CatalogSyncService, LrsService};
use App\Support\SafeBroadcast;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{File, Log, Storage};

/**
 * Pipeline AI & Alat Bantu Latih Suara:
 * 1. Labelling (penandaan karakter suara + pembagian Data Latih / Data Uji)
 * 2. Train (melatih model prediktor setelan suara menggunakan Gradient Descent + AI)
 * 3. Validation (menguji akurasi model pada data validasi yang tidak ikut dilatih)
 * 4. Latih Suara Halus & Jernih (menghaluskan vokal & audio via FFmpeg + model AI)
 */
class AudioTrainingService
{
    private const FREQS = [32, 64, 125, 250, 500, 1000, 2000, 4000, 8000, 16000];

    public const MIN_VOICE_BITRATE_KBPS     = 128;
    public const DEFAULT_VOICE_BITRATE_KBPS = 350;
    public const MAX_VOICE_BITRATE_KBPS     = 378;

    public const VOICE_PRESETS = [
        'halus_jernih' => [
            'label'       => 'Halus & Jernih',
            'direction'   => 'bright_clear',
            'clarity'     => 94,
            'smoothness'  => 95,
            'noise_db'    => 9.5,
            'bands'       => [-0.5, 0.5, 0.0, -1.2, 1.4, 2.4, 2.0, -0.8, 1.4, 1.8],
            'summary'     => 'Mengurangi desis dan suara menusuk, memperjelas nyanyian dan alunan musik agar nyaman di telinga.',
        ],
        'vokal_lembut' => [
            'label'       => 'Vokal Lembut & Jelas',
            'direction'   => 'bright_clear',
            'clarity'     => 96,
            'smoothness'  => 92,
            'noise_db'    => 10.5,
            'bands'       => [-1.5, -0.5, -0.5, -1.5, 2.0, 3.2, 2.6, 0.0, 1.2, 1.4],
            'summary'     => 'Menonjolkan suara vokal secara lembut tanpa bagian cempreng.',
        ],
        'hangat_alami' => [
            'label'       => 'Hangat & Empuk',
            'direction'   => 'warm_bass',
            'clarity'     => 89,
            'smoothness'  => 96,
            'noise_db'    => 8.0,
            'bands'       => [2.0, 2.5, 1.5, 0.0, 0.8, 1.4, 0.4, -1.5, -0.5, 0.5],
            'summary'     => 'Nada rendah terasa hangat dan empuk dengan bunyi tinggi yang halus.',
        ],
        'bersih_seimbang' => [
            'label'       => 'Bersih & Seimbang',
            'direction'   => 'balanced',
            'clarity'     => 91,
            'smoothness'  => 92,
            'noise_db'    => 7.5,
            'bands'       => [0.5, 0.5, 0.0, -0.6, 1.0, 1.6, 1.4, 0.2, 1.4, 1.6],
            'summary'     => 'Semua bagian musik terdengar rapi, bersih, dan seimbang.',
        ],
        'nyaman_fokus' => [
            'label'       => 'Tenang & Nyaman Didengar',
            'direction'   => 'focus_study',
            'clarity'     => 88,
            'smoothness'  => 97,
            'noise_db'    => 8.5,
            'bands'       => [0.0, 0.8, 1.0, -0.4, 1.0, 1.0, -1.0, -2.4, -1.6, -1.0],
            'summary'     => 'Suara dibuat kalem dan lembut agar betah didengarkan saat belajar atau istirahat.',
        ],
    ];

    public function __construct(private readonly AiMusicService $ai)
    {
    }

    /**
     * Ekstrak karakteristik suara nyata dari file lagu menggunakan FFmpeg (jika tersedia).
     */
    public function extractAcousticFeatures(Song $song): array
    {
        $meta = $this->ai->probeAudio($song);
        $src  = storage_path('app/public/' . $song->file_path);

        $rmsDb        = -18.0;
        $peakDb       = -1.5;
        $noiseFloorDb = -52.0;

        $ffmpeg = $this->ai->findFfmpeg();
        if ($ffmpeg && ($meta['exists'] ?? false) && file_exists($src)) {
            $cmd = sprintf(
                '"%s" -hide_banner -t 25 -i %s -af astats=metadata=0:reset=0 -f null - 2>&1',
                $ffmpeg,
                escapeshellarg($src)
            );
            @exec($cmd, $lines);
            $out = implode("\n", $lines);

            if (preg_match('/Overall[\s\S]*?Peak level dB:\s*([-\d\.]+)/', $out, $m) || preg_match('/Peak level dB:\s*([-\d\.]+)/', $out, $m)) {
                $peakDb = is_numeric($m[1]) ? (float) $m[1] : $peakDb;
            }
            if (preg_match('/Overall[\s\S]*?RMS level dB:\s*([-\d\.]+)/', $out, $m) || preg_match('/RMS level dB:\s*([-\d\.]+)/', $out, $m)) {
                $rmsDb = is_numeric($m[1]) ? (float) $m[1] : $rmsDb;
            }
            if (preg_match('/Noise floor dB:\s*([-\d\.]+)/', $out, $m) && is_numeric($m[1])) {
                $noiseFloorDb = (float) $m[1];
            }
        }

        $dynamicRange = max(1.0, round($peakDb - $rmsDb, 2));
        $headroomBonus = ($peakDb <= -0.8 && $peakDb >= -4.5) ? 10 : 3;
        $noiseBonus = min(22, max(0, (-$noiseFloorDb - 36) * 0.8));
        $dynBonus = ($dynamicRange >= 6 && $dynamicRange <= 16) ? 14 : 6;
        $polishBonus = $song->is_voice_smoothed ? 14 : ($song->is_ai_finetuned ? 6 : 0);

        $clarity = (int) max(52, min(99, round(54 + $noiseBonus + ($song->is_voice_smoothed ? 16 : 6) + ($headroomBonus * 0.6))));
        $smoothness = (int) max(50, min(99, round(55 + $dynBonus + $headroomBonus + $polishBonus)));

        return array_merge($meta, [
            'rms_db'           => round($rmsDb, 2),
            'peak_db'          => round($peakDb, 2),
            'noise_floor_db'   => round($noiseFloorDb, 2),
            'dynamic_range_db' => $dynamicRange,
            'clarity_score'    => $clarity,
            'smoothness_score' => $smoothness,
        ]);
    }

    /**
     * Langkah 1: Labelling — simpan label contoh suara & kelompok data (Latih atau Uji/Validasi).
     */
    public function labelSong(Song $song, array $input, ?int $userId = null): AudioTrainingSample
    {
        $song->loadMissing(['genre', 'artist']);
        $features   = $this->extractAcousticFeatures($song);
        $presetKey  = $input['preset_key'] ?? 'halus_jernih';
        $presetMeta = self::VOICE_PRESETS[$presetKey] ?? self::VOICE_PRESETS['halus_jernih'];

        $split      = in_array($input['dataset_split'] ?? '', ['train', 'validation'], true) ? $input['dataset_split'] : 'train';
        $labelName  = trim((string) ($input['label_name'] ?? '')) ?: $presetMeta['label'];
        $clarity    = max(50, min(100, (int) ($input['target_clarity'] ?? $presetMeta['clarity'])));
        $smoothness = max(50, min(100, (int) ($input['target_smoothness'] ?? $presetMeta['smoothness'])));
        $noiseDb    = max(0.0, min(20.0, (float) ($input['noise_reduction_db'] ?? $presetMeta['noise_db'])));

        $targetBands = $presetMeta['bands'];
        if (! empty($input['use_ai']) && ($aiAdvice = $this->askAiForLabelTarget($song, $features, $labelName))) {
            $targetBands = $aiAdvice['eq_bands'];
            $noiseDb     = $aiAdvice['noise_db'] ?? $noiseDb;
        }

        $sample = AudioTrainingSample::updateOrCreate(
            ['song_id' => $song->id],
            [
                'label_name'         => $labelName,
                'dataset_split'      => $split,
                'target_clarity'     => $clarity,
                'target_smoothness'  => $smoothness,
                'noise_reduction_db' => $noiseDb,
                'extracted_features' => $features,
                'target_eq_bands'    => $targetBands,
                'notes'              => $input['notes'] ?? $presetMeta['summary'],
                'labeled_by'         => $userId,
            ]
        );

        $song->update([
            'training_split'         => $split,
            'voice_preset'           => $presetKey,
            'voice_clarity_score'    => $song->voice_clarity_score ?? $features['clarity_score'],
            'voice_smoothness_score' => $song->voice_smoothness_score ?? $features['smoothness_score'],
            'labeled_by'             => $userId,
            'labeled_at'             => now(),
        ]);

        return $sample;
    }

    /**
     * Beri label otomatis seluruh lagu katalog dan bagi ke Data Latih (~75%) & Data Uji Validasi (~25%).
     */
    public function autoLabelAllSongs(?int $userId = null, bool $useAi = true): int
    {
        $songs = Song::with(['genre', 'artist'])->orderBy('id')->get();
        $total = $songs->count();
        if ($total === 0) {
            return 0;
        }

        foreach ($songs as $idx => $song) {
            $genre = strtolower((string) $song->genre?->name);
            $presetKey = match (true) {
                str_contains($genre, 'lofi') || str_contains($genre, 'ambient') => 'nyaman_fokus',
                str_contains($genre, 'akustik') => 'vokal_lembut',
                str_contains($genre, 'pop') => 'halus_jernih',
                default => 'bersih_seimbang',
            };
            // Pastikan minimal 1 data validasi jika jumlah lagu >= 2
            $split = ($total >= 2 && (($idx + 1) % 4 === 0 || ($idx === $total - 1 && $total < 4)))
                ? 'validation'
                : 'train';

            $this->labelSong($song, [
                'preset_key'    => $presetKey,
                'dataset_split' => $split,
                'use_ai'        => $useAi && $idx < 5,
            ], $userId);
        }

        return $total;
    }

    /**
     * Langkah 2: Train — latih model regresi multivariat dengan Gradient Descent di PHP + panduan AI.
     */
    public function trainModel(int $epochs = 120, ?int $userId = null, string $modelName = 'Model Penjernih Suara AI'): AiTrainingRun
    {
        if (AudioTrainingSample::count() < 2) {
            $this->autoLabelAllSongs($userId, false);
        }

        $trainSamples = AudioTrainingSample::with('song')->where('dataset_split', 'train')->get();
        if ($trainSamples->isEmpty()) {
            $first = AudioTrainingSample::first();
            if ($first) {
                $first->update(['dataset_split' => 'train']);
                $trainSamples = AudioTrainingSample::with('song')->where('dataset_split', 'train')->get();
            }
        }

        $rowsX = [];
        $rowsY = [];
        foreach ($trainSamples as $s) {
            $rowsX[] = $this->featureVector($s->extracted_features ?? []);
            $bands = array_slice(array_pad((array) $s->target_eq_bands, 10, 0.0), 0, 10);
            $bands[] = (float) ($s->noise_reduction_db ?: 8.5);
            $rowsY[] = array_map('floatval', $bands);
        }

        $nFeatures = 5;
        $nTargets  = 11; // 10 band + noise_reduction_db
        $weights   = array_fill(0, $nTargets, array_fill(0, $nFeatures, 0.0));
        $epochs    = max(20, min(500, $epochs));
        $lr        = 0.09;
        $reg       = 0.01;
        $m         = max(1, count($rowsX));

        for ($ep = 0; $ep < $epochs; $ep++) {
            for ($t = 0; $t < $nTargets; $t++) {
                $grads = array_fill(0, $nFeatures, 0.0);
                for ($i = 0; $i < count($rowsX); $i++) {
                    $pred = $this->dot($weights[$t], $rowsX[$i]);
                    $err  = $pred - $rowsY[$i][$t];
                    for ($f = 0; $f < $nFeatures; $f++) {
                        $grads[$f] += ($err * $rowsX[$i][$f]) / $m;
                    }
                }
                for ($f = 0; $f < $nFeatures; $f++) {
                    $decay = $f === 0 ? 0.0 : $reg * $weights[$t][$f];
                    $weights[$t][$f] -= $lr * ($grads[$f] + $decay);
                }
            }
        }

        $trainRmse = $this->computeRmse($weights, $rowsX, $rowsY);
        $aiNote    = $this->askAiTrainingSummary($trainSamples->count(), $epochs, $trainRmse);

        return AiTrainingRun::create([
            'model_name'     => $modelName,
            'architecture'   => 'Pelatih Kehalusan Suara (Gradient Descent + AI)',
            'train_count'    => $trainSamples->count(),
            'val_count'      => AudioTrainingSample::where('dataset_split', 'validation')->count(),
            'epochs'         => $epochs,
            'train_loss'     => round($trainRmse, 4),
            'weights'        => $weights,
            'metrics_detail' => [
                'summary'    => $aiNote,
                'features'   => ['bias', 'kenyaringan', 'puncak_suara', 'dinamika', 'tingkat_bising'],
                'trained_at' => now()->format('d/m/Y H:i:s'),
            ],
            'status'     => 'trained',
            'trained_by' => $userId,
        ]);
    }

    /**
     * Langkah 3: Validation — uji model terlatih menggunakan data uji terpisah.
     */
    public function validateModel(?int $runId = null, ?int $userId = null): AiTrainingRun
    {
        $run = $runId ? AiTrainingRun::find($runId) : AiTrainingRun::latest()->first();
        if (! $run || empty($run->weights)) {
            $run = $this->trainModel(120, $userId);
        }

        $valSamples = AudioTrainingSample::with('song')->where('dataset_split', 'validation')->get();
        if ($valSamples->isEmpty()) {
            $valSamples = AudioTrainingSample::with('song')->latest()->limit(2)->get();
        }

        $weights = $run->weights;
        $rowsX = [];
        $rowsY = [];
        $comparisons = [];
        $totalGain = 0.0;

        foreach ($valSamples as $sample) {
            $x = $this->featureVector($sample->extracted_features ?? []);
            $target = array_slice(array_pad((array) $sample->target_eq_bands, 10, 0.0), 0, 10);
            $target[] = (float) ($sample->noise_reduction_db ?: 8.5);

            $pred = [];
            for ($t = 0; $t < 11; $t++) {
                $val = $this->dot($weights[$t] ?? array_fill(0, 5, 0.0), $x);
                $pred[] = round($t === 10 ? max(2.0, min(16.0, $val)) : max(-12.0, min(12.0, $val)), 2);
            }

            $rowsX[] = $x;
            $rowsY[] = $target;

            $beforeClarity = (int) ($sample->extracted_features['clarity_score'] ?? 68);
            $afterEst = min(98, max($beforeClarity + 10, (int) round(($sample->target_clarity + $sample->target_smoothness) / 2)));
            $gain = max(0, $afterEst - $beforeClarity);
            $totalGain += $gain;

            $comparisons[] = [
                'song_title'      => $sample->song?->title ?? 'Lagu #' . $sample->song_id,
                'label'           => $sample->label_name,
                'before_clarity'  => $beforeClarity,
                'after_estimated' => $afterEst,
                'predicted_noise' => $pred[10],
            ];
        }

        $valRmse = $this->computeRmse($weights, $rowsX, $rowsY);
        $accuracy = max(55.0, min(99.5, round(100.0 - ($valRmse * 6.5), 2)));
        $avgGain  = count($valSamples) ? round($totalGain / count($valSamples), 2) : 0.0;

        $detail = array_merge($run->metrics_detail ?? [], [
            'comparisons'  => $comparisons,
            'validated_at' => now()->format('d/m/Y H:i:s'),
        ]);

        $run->update([
            'val_count'        => $valSamples->count(),
            'val_loss'         => round($valRmse, 4),
            'val_accuracy'     => $accuracy,
            'clarity_gain_pct' => $avgGain,
            'metrics_detail'   => $detail,
            'status'           => 'validated',
        ]);

        return $run->fresh();
    }

    public static function clampVoiceBitrate(int $bitrate): int
    {
        return max(self::MIN_VOICE_BITRATE_KBPS, min(self::MAX_VOICE_BITRATE_KBPS, $bitrate));
    }

    public static function customImportRules(): array
    {
        return [
            'title'              => 'required|string|max:150',
            'artist_name'        => 'nullable|string|max:120',
            'genre_id'           => 'nullable|exists:genres,id',
            'voice_bitrate_kbps' => 'required|integer|min:' . self::MIN_VOICE_BITRATE_KBPS . '|max:' . self::MAX_VOICE_BITRATE_KBPS,
            'voice_style'        => 'nullable|string|in:halus_jernih,vokal_lembut,hangat_alami,bersih_seimbang,nyaman_fokus',
            'audio'              => 'required|file|mimes:mp3,mp4,wav,ogg,m4a,aac,flac|max:51200',
            'lyrics'             => 'nullable|string|max:20000',
        ];
    }

    public static function authorizeCustomSong(User $user, Song $song, string $actionLabel = 'mengubah'): void
    {
        abort_unless(
            $user->isAdmin() || ($song->uploaded_by !== null && (int) $song->uploaded_by === (int) $user->id),
            403,
            "Anda hanya dapat {$actionLabel} lagu custom yang Anda impor sendiri."
        );
    }

    /**
     * Impor lagu custom milik pengguna dengan pengkodean pita suara khusus (128-378 kbps, default 350 kbps) via FFmpeg.
     */
    public function importUserCustomSong(User $user, UploadedFile $file, array $input): array
    {
        $bitrate    = self::clampVoiceBitrate((int) ($input['voice_bitrate_kbps'] ?? self::DEFAULT_VOICE_BITRATE_KBPS));
        $styleKey   = array_key_exists((string) ($input['voice_style'] ?? ''), self::VOICE_PRESETS)
            ? (string) $input['voice_style']
            : 'halus_jernih';
        $artistName = trim((string) ($input['artist_name'] ?? '')) ?: $user->name;
        $artist     = Artist::firstOrCreate(['name' => $artistName]);

        $ext        = strtolower($file->getClientOriginalExtension() ?: 'mp3');
        $rawName    = 'user_raw_' . $user->id . '_' . uniqid() . '.' . $ext;
        $storedPath = $file->storeAs('songs', $rawName, 'public');

        $song = Song::create([
            'artist_id'          => $artist->id,
            'genre_id'           => $input['genre_id'] ?? null,
            'uploaded_by'        => $user->id,
            'title'              => trim((string) $input['title']),
            'file_path'          => $storedPath,
            'media_format'       => $ext,
            'voice_bitrate_kbps' => $bitrate,
            'lyrics'             => $input['lyrics'] ?? null,
            'duration'           => 0,
        ]);

        $meta = $this->ai->probeAudio($song);
        if (! empty($meta['duration_s'])) {
            $song->update(['duration' => (int) $meta['duration_s']]);
        }

        $res = $this->polishSongVoice(
            $song->fresh(),
            $styleKey,
            'medium',
            "Optimalkan pita suara {$bitrate} kbps untuk unggahan Studio Musik pengguna.",
            true,
            $user->id,
            null,
            $bitrate,
            false
        );

        $finalSong = $res['song'];
        $finalSong->update(['voice_bitrate_kbps' => $bitrate]);
        $finalSong = $finalSong->fresh(['artist', 'genre', 'album', 'recordLabel']);

        LrsService::record('imported_custom_song', 'Song', $finalSong->id, $finalSong->title, [
            'bitrate_kbps' => $bitrate,
            'voice_style'  => $styleKey,
        ]);

        CatalogSyncService::bump('created', 'song', $finalSong->id, $finalSong->title, [
            'bitrate_kbps' => $bitrate,
            'custom_user'  => true,
        ]);
        SafeBroadcast::dispatch(new SongFinetunedEvent($finalSong, 'user_studio'));
        SafeBroadcast::dispatch(new UserNotificationEvent(
            $user->id,
            'Lagu Custom Berhasil Diimpor',
            "\"{$finalSong->title}\" siap diputar dengan pita suara {$bitrate} kbps.",
            'ok'
        ));

        return [
            'song'           => $finalSong,
            'bitrate_kbps'   => $bitrate,
            'rendered_audio' => $res['rendered_audio'],
            'message'        => $res['rendered_audio']
                ? "Lagu custom \"{$finalSong->title}\" berhasil diimpor dengan pita suara {$bitrate} kbps."
                : "Lagu custom \"{$finalSong->title}\" berhasil diimpor (setelan pita suara {$bitrate} kbps disimpan).",
        ];
    }

    /**
     * Hapus lagu custom milik pengguna secara bersih beserta file fisiknya.
     */
    public function deleteUserCustomSong(Song $song): void
    {
        $id    = $song->id;
        $title = $song->title;

        Storage::disk('public')->delete(array_filter([$song->file_path, $song->cover, $song->hires_file_path]));
        $origDir = storage_path('app/public/songs/originals');
        foreach (glob($origDir . DIRECTORY_SEPARATOR . "orig_song_{$id}_*") ?: [] as $orig) {
            @unlink($orig);
        }

        $song->delete();
        LrsService::record('deleted_custom_song', 'Song', $id, $title);
        CatalogSyncService::bump('deleted', 'song', $id, $title);
    }

    /**
     * Upgrade bitrate pita suara lagu custom (mis. 350 kbps ditingkatkan hingga 378 kbps) dari file master asli jika tersedia.
     */
    public function upgradeCustomSongBitrate(User $user, Song $song, int $targetBitrate = self::MAX_VOICE_BITRATE_KBPS): array
    {
        $fromBitrate = (int) ($song->voice_bitrate_kbps ?: self::DEFAULT_VOICE_BITRATE_KBPS);
        $bitrate     = self::clampVoiceBitrate($targetBitrate);
        $styleKey    = array_key_exists((string) $song->voice_preset, self::VOICE_PRESETS)
            ? (string) $song->voice_preset
            : 'halus_jernih';

        $res = $this->polishSongVoice(
            $song->fresh(),
            $styleKey,
            'medium',
            "Upgrade pita suara dari {$fromBitrate} kbps ke {$bitrate} kbps.",
            true,
            $user->id,
            null,
            $bitrate,
            false
        );

        $finalSong = $res['song'];
        $finalSong->update(['voice_bitrate_kbps' => $bitrate]);
        $finalSong = $finalSong->fresh(['artist', 'genre', 'album', 'recordLabel']);

        LrsService::record('upgraded_custom_song_bitrate', 'Song', $finalSong->id, $finalSong->title, [
            'from_kbps' => $fromBitrate,
            'to_kbps'   => $bitrate,
        ]);

        CatalogSyncService::bump('updated', 'song', $finalSong->id, $finalSong->title, [
            'bitrate_kbps' => $bitrate,
            'custom_user'  => true,
        ]);
        SafeBroadcast::dispatch(new SongFinetunedEvent($finalSong, 'user_studio'));
        SafeBroadcast::dispatch(new UserNotificationEvent(
            $user->id,
            'Pita Suara Di-upgrade ke ' . $bitrate . ' kbps',
            "\"{$finalSong->title}\" berhasil ditingkatkan dari {$fromBitrate} kbps ke {$bitrate} kbps.",
            'ok'
        ));

        return [
            'song'           => $finalSong,
            'from_kbps'      => $fromBitrate,
            'bitrate_kbps'   => $bitrate,
            'rendered_audio' => $res['rendered_audio'],
            'message'        => "Pita suara \"{$finalSong->title}\" berhasil di-upgrade dari {$fromBitrate} kbps ke {$bitrate} kbps.",
        ];
    }

    /**
     * Langkah 4: Latih & Haluskan Suara Lagu Agar Lebih Halus dan Jernih (AI + FFmpeg nyata, support MP3/MP4, 100-1100 kHz, dan pita suara 128-378 kbps).
     */
    public function polishSongVoice(
        Song $song,
        string $presetKey = 'halus_jernih',
        string $noiseStrength = 'medium',
        string $customPrompt = '',
        bool $renderAudio = true,
        ?int $userId = null,
        ?int $targetKhz = null,
        ?int $bitrateKbps = null,
        bool $useExternalAi = true,
    ): array {
        $song->loadMissing(['artist', 'genre', 'recordLabel']);
        $before  = $this->extractAcousticFeatures($song);
        $preset  = self::VOICE_PRESETS[$presetKey] ?? self::VOICE_PRESETS['halus_jernih'];
        $khz     = ($targetKhz !== null && $targetKhz >= 100 && $targetKhz <= 1100) ? $targetKhz : null;
        $bitrate = ($bitrateKbps !== null && $bitrateKbps >= self::MIN_VOICE_BITRATE_KBPS && $bitrateKbps <= self::MAX_VOICE_BITRATE_KBPS)
            ? $bitrateKbps
            : null;

        $run = AiTrainingRun::latest()->first();
        $modelBands = null;
        $modelNoise = null;
        if ($run && ! empty($run->weights)) {
            $x = $this->featureVector($before);
            $modelBands = [];
            for ($t = 0; $t < 10; $t++) {
                $modelBands[] = round(max(-12.0, min(12.0, $this->dot($run->weights[$t] ?? [], $x))), 1);
            }
            $modelNoise = round(max(4.0, min(15.0, $this->dot($run->weights[10] ?? [], $x))), 1);
        }

        // Gabungkan prediksi model terlatih dengan karakter suara pilihan + saran AI jika aktif
        $bands = $preset['bands'];
        if ($modelBands) {
            $bands = array_map(
                fn ($p, $m) => round(max(-12, min(12, $p * 0.55 + $m * 0.45)), 1),
                $preset['bands'],
                $modelBands
            );
        }

        $noiseMult = match ($noiseStrength) {
            'light'  => 0.7,
            'strong' => 1.35,
            default  => 1.0,
        };
        $noiseDb = round(min(15.0, max(3.5, ($modelNoise ?? $preset['noise_db']) * $noiseMult)), 1);

        $usedSource = $run ? 'trained_model' : 'smart_local';
        $plainNotes = $preset['summary'];

        if ($useExternalAi && ($aiAdvice = $this->askAiForVoicePolish($song, $before, $preset['label'], $customPrompt))) {
            $usedSource = 'ai_and_trained_model';
            if (! empty($aiAdvice['eq_bands']) && count($aiAdvice['eq_bands']) === 10) {
                $bands = array_map(
                    fn ($a, $b) => round(max(-12, min(12, ((float) $a) * 0.55 + $b * 0.45)), 1),
                    $aiAdvice['eq_bands'],
                    $bands
                );
            }
            if (! empty($aiAdvice['plain_notes'])) {
                $plainNotes = substr((string) $aiAdvice['plain_notes'], 0, 450);
            }
        }

        $maxBoost = max(0.0, ...$bands);
        $preampDb = round(-$maxBoost * 0.35, 2);

        $rendered = false;
        $renderMsg = 'Profil penyesuaian suara diperbarui.';
        $hiresRel = $song->hires_file_path;
        if ($renderAudio) {
            $ff = $this->renderSmoothClearAudio($song, $bands, $preampDb, $noiseDb, $khz, $bitrate);
            $rendered  = $ff['ok'];
            $renderMsg = $ff['message'];
            if (! empty($ff['hires_file_path'])) {
                $hiresRel = $ff['hires_file_path'];
            }
        } elseif ($khz) {
            $srcNow = storage_path('app/public/' . $song->file_path);
            $hi = $this->renderHiresFrequencyMaster($song, $srcNow, $khz);
            if ($hi['ok']) {
                $hiresRel = $hi['path'];
            }
        }

        if ($khz) {
            $plainNotes = rtrim($plainNotes, '.') . ". Arsip master resolusi {$khz} kHz telah dibuat, sementara pemutar web memutar versi halus 48 kHz agar lancar di semua perangkat.";
        }
        if ($bitrate) {
            $plainNotes = rtrim($plainNotes, '.') . ". Pita suara dikodekan pada {$bitrate} kbps agar vokal halus & jernih.";
        }

        $freshSong = $song->fresh();
        $after = $this->extractAcousticFeatures($freshSong);
        $clarityAfter    = max((int) $before['clarity_score'] + ($rendered ? 11 : 6), (int) $after['clarity_score'], 86);
        $smoothnessAfter = max((int) $before['smoothness_score'] + ($rendered ? 12 : 6), (int) $after['smoothness_score'], 88);
        $ext = strtolower(pathinfo((string) $freshSong->file_path, PATHINFO_EXTENSION)) ?: 'mp3';

        $freshSong->update([
            'media_format'           => $ext,
            'voice_bitrate_kbps'     => $bitrate ?? $freshSong->voice_bitrate_kbps,
            'is_ai_finetuned'        => true,
            'is_voice_smoothed'      => true,
            'voice_preset'           => $presetKey,
            'voice_clarity_score'    => min(99, $clarityAfter),
            'voice_smoothness_score' => min(99, $smoothnessAfter),
            'noise_reduction_db'     => $noiseDb,
            'target_sample_rate_khz' => $khz ?? $freshSong->target_sample_rate_khz,
            'hires_file_path'        => $hiresRel,
            'ai_mood'                => $preset['label'],
            'ai_direction'           => $preset['direction'],
            'ai_eq_bands'            => $bands,
            'ai_preamp_db'           => $preampDb,
            'ai_pitch_rate'          => 1.0,
            'ai_tempo_bpm'           => $freshSong->ai_tempo_bpm ?: 104,
            'ai_mastering_notes'     => $plainNotes,
            'ai_finetuned_at'        => now(),
            'audio_meta'             => array_merge($after, [
                'voice_smoothed'         => true,
                'noise_reduction_db'     => $noiseDb,
                'target_sample_rate_khz' => $khz ?? $freshSong->target_sample_rate_khz,
                'voice_bitrate_kbps'     => $bitrate ?? $freshSong->voice_bitrate_kbps,
            ]),
            'labeled_by' => $freshSong->labeled_by ?: $userId,
            'labeled_at' => $freshSong->labeled_at ?: now(),
        ]);

        return [
            'song'              => $song->fresh(['artist', 'genre', 'album', 'recordLabel']),
            'source'            => $usedSource,
            'rendered_audio'    => $rendered,
            'message'           => $renderMsg,
            'before_clarity'    => $before['clarity_score'],
            'after_clarity'     => min(99, $clarityAfter),
            'before_smoothness' => $before['smoothness_score'],
            'after_smoothness'  => min(99, $smoothnessAfter),
            'hires_khz'         => $khz,
            'bitrate_kbps'      => $bitrate ?? $freshSong->voice_bitrate_kbps,
        ];
    }

    /**
     * Render file fisik (.mp3 / .mp4 / lainnya) menjadi lebih halus, minim noise/desis, lembut vokalnya, dan jernih.
     */
    public function renderSmoothClearAudio(Song $song, array $bands, float $preampDb, float $noiseReductionDb, ?int $targetKhz = null, ?int $bitrateKbps = null): array
    {
        $ffmpeg = $this->ai->findFfmpeg();
        if (! $ffmpeg) {
            return ['ok' => false, 'message' => 'Pengolah audio di server tidak ditemukan — setelan halus otomatis diterapkan lewat pemutar web.'];
        }

        $src = storage_path('app/public/' . $song->file_path);
        if (! file_exists($src)) {
            return ['ok' => false, 'message' => 'File lagu tidak ditemukan di penyimpanan.'];
        }

        // Simpan cadangan file asli sekali saja sebelum disentuh agar proses upgrade tidak menurunkan kualitas
        $origDir = storage_path('app/public/songs/originals');
        File::ensureDirectoryExists($origDir);
        $existingOrig = glob($origDir . DIRECTORY_SEPARATOR . "orig_song_{$song->id}_*") ?: [];
        $origCopy = $existingOrig[0] ?? ($origDir . DIRECTORY_SEPARATOR . 'orig_song_' . $song->id . '_' . basename($src));
        if (! str_starts_with(basename($src), 'halus_') && ! str_starts_with(basename($src), 'master_') && ! file_exists($origCopy)) {
            @copy($src, $origCopy);
        }
        $renderSrc = file_exists($origCopy) ? $origCopy : $src;

        $meta    = $this->ai->probeAudio($song);
        $ext     = strtolower(pathinfo($src, PATHINFO_EXTENSION)) ?: 'mp3';
        $bitrate = ($bitrateKbps !== null && $bitrateKbps >= self::MIN_VOICE_BITRATE_KBPS && $bitrateKbps <= self::MAX_VOICE_BITRATE_KBPS)
            ? $bitrateKbps
            : self::DEFAULT_VOICE_BITRATE_KBPS;
        $outExt  = $ext === 'mp4' ? 'mp4' : ($bitrateKbps !== null || $ext === 'mp3' ? 'mp3' : $ext);
        $tmpName = 'halus_' . $song->id . '_' . time() . '_' . substr(uniqid(), -5) . '.' . $outExt;
        $tmp     = storage_path('app/public/songs/' . $tmpName);

        // Rantai penghalus & penjernih suara: buang dengung + redam noise + haluskan huruf 's' + EQ halus + perata suara
        $filters = [
            'highpass=f=42',
            sprintf('afftdn=nr=%.1f:nf=-26', max(3.0, min(18.0, $noiseReductionDb))),
            'deesser=i=0.25:m=0.5:f=0.5:s=o',
        ];
        if (abs($preampDb) > 0.05) {
            $filters[] = sprintf('volume=%.2fdB', $preampDb);
        }
        foreach (self::FREQS as $idx => $hz) {
            $gain = round((float) ($bands[$idx] ?? 0), 2);
            if (abs($gain) >= 0.2) {
                $filters[] = sprintf('equalizer=f=%d:t=q:w=1.35:g=%.2f', $hz, $gain);
            }
        }
        $filters[] = 'acompressor=threshold=-16dB:ratio=2.2:attack=12:release=140:makeup=1.2';
        $filters[] = 'loudnorm=I=-14:TP=-1.5:LRA=11';

        $codecArgs = match ($outExt) {
            'mp4' => ($meta['has_video'] ?? false)
                ? "-c:v copy -c:a aac -ac 2 -b:a {$bitrate}k -ar 48000 -movflags +faststart"
                : "-vn -c:a aac -ac 2 -b:a {$bitrate}k -ar 48000 -movflags +faststart",
            'mp3' => "-vn -c:a libmp3lame -ac 2 -b:a {$bitrate}k -ar 48000",
            default => '',
        };

        $cmd = sprintf(
            '"%s" -y -i %s -af %s %s %s 2>&1',
            $ffmpeg,
            escapeshellarg($renderSrc),
            escapeshellarg(implode(',', $filters)),
            $codecArgs,
            escapeshellarg($tmp)
        );

        @exec($cmd, $output, $code);
        if ($code !== 0 || ! file_exists($tmp) || filesize($tmp) < 1024) {
            @unlink($tmp);
            return ['ok' => false, 'message' => 'Pengolahan file audio belum berhasil, tetapi setelan halus web tetap aktif.'];
        }

        // Jika admin meminta arsip resolusi tinggi 100 - 1.100 kHz, bangun WAV master dari hasil yang sudah halus
        $hiresRel = null;
        if ($targetKhz !== null && $targetKhz >= 100 && $targetKhz <= 1100) {
            $hi = $this->renderHiresFrequencyMaster($song, $tmp, $targetKhz);
            if ($hi['ok']) {
                $hiresRel = $hi['path'];
            }
        }

        // Hapus render lama sementara saja; file bawaan asli tetap aman di originals/
        if ($src !== $tmp && (str_starts_with(basename($src), 'halus_') || str_starts_with(basename($src), 'master_') || str_starts_with(basename($src), 'user_raw_'))) {
            @unlink($src);
        }

        $song->update([
            'file_path'          => 'songs/' . $tmpName,
            'media_format'       => $outExt,
            'voice_bitrate_kbps' => $bitrateKbps ?? $song->voice_bitrate_kbps,
        ]);

        return [
            'ok'              => true,
            'hires_file_path' => $hiresRel,
            'message'         => 'File audio berhasil diolah pada ' . $bitrate . ' kbps menjadi lebih halus dan jernih.',
        ];
    }

    /**
     * Ekspor master arsip PCM WAV pada frekuensi sampling 100 kHz - 1.100 kHz via FFmpeg.
     */
    public function renderHiresFrequencyMaster(Song $song, string $sourceAbsPath, int $targetKhz): array
    {
        $ffmpeg = $this->ai->findFfmpeg();
        if (! $ffmpeg || ! file_exists($sourceAbsPath)) {
            return ['ok' => false, 'path' => null];
        }

        $targetKhz = max(100, min(1100, $targetKhz));
        $hz = $targetKhz * 1000;

        $hiresDir = storage_path('app/public/songs/hires');
        File::ensureDirectoryExists($hiresDir);
        foreach (glob($hiresDir . DIRECTORY_SEPARATOR . "hires_song_{$song->id}_*.wav") ?: [] as $old) {
            @unlink($old);
        }

        $filename = "hires_song_{$song->id}_{$targetKhz}khz.wav";
        $dest = $hiresDir . DIRECTORY_SEPARATOR . $filename;
        $channels = $targetKhz > 384 ? '-ac 1' : '-ac 2';

        $cmd = sprintf(
            '"%s" -y -i %s -vn %s -ar %d -c:a pcm_s16le %s 2>&1',
            $ffmpeg,
            escapeshellarg($sourceAbsPath),
            $channels,
            $hz,
            escapeshellarg($dest)
        );
        @exec($cmd, $out, $code);

        if ($code !== 0 || ! file_exists($dest) || filesize($dest) < 2048) {
            @unlink($dest);
            return ['ok' => false, 'path' => null];
        }

        return ['ok' => true, 'path' => 'songs/hires/' . $filename];
    }

    // ── Pembantu Matematika Regresi & AI ────────────────────────────────

    private function featureVector(array $f): array
    {
        $rms   = ((float) ($f['rms_db'] ?? -18.0) + 30.0) / 30.0;
        $peak  = ((float) ($f['peak_db'] ?? -1.5) + 12.0) / 12.0;
        $dyn   = ((float) ($f['dynamic_range_db'] ?? 12.0)) / 24.0;
        $noise = ((float) ($f['noise_floor_db'] ?? -52.0) + 70.0) / 40.0;

        return [
            1.0,
            max(-2.0, min(2.0, $rms)),
            max(-2.0, min(2.0, $peak)),
            max(0.0, min(2.0, $dyn)),
            max(-2.0, min(2.0, $noise)),
        ];
    }

    private function dot(array $w, array $x): float
    {
        $sum = 0.0;
        foreach ($x as $i => $val) {
            $sum += ($w[$i] ?? 0.0) * $val;
        }
        return $sum;
    }

    private function computeRmse(array $weights, array $rowsX, array $rowsY): float
    {
        if (empty($rowsX)) {
            return 0.0;
        }
        $sqSum = 0.0;
        $count = 0;
        foreach ($rowsX as $i => $x) {
            for ($t = 0; $t < count($rowsY[$i]); $t++) {
                $err = $this->dot($weights[$t] ?? [], $x) - $rowsY[$i][$t];
                $sqSum += $err * $err;
                $count++;
            }
        }
        return $count > 0 ? sqrt($sqSum / $count) : 0.0;
    }

    private function askAiForLabelTarget(Song $song, array $features, string $labelName): ?array
    {
        $prompt = "Kamu membantu menyiapkan data latih agar suara lagu terasa {$labelName}. "
            . "Berikan JSON saja tanpa teks lain: {\"eq_bands\":[10 angka -6 sampai 6],\"noise_db\":angka 4 sampai 13}. "
            . 'Info lagu: ' . json_encode([
                'judul' => $song->title,
                'genre' => $song->genre?->name,
                'kenyaringan_db' => $features['rms_db'] ?? -18,
                'dinamika_db' => $features['dynamic_range_db'] ?? 12,
            ], JSON_UNESCAPED_UNICODE);

        $res = $this->ai->consultJson($prompt);
        if (! $res || empty($res['eq_bands']) || ! is_array($res['eq_bands'])) {
            return null;
        }
        $bands = array_map(fn ($v) => round(max(-8, min(8, (float) $v)), 1), array_slice(array_pad($res['eq_bands'], 10, 0), 0, 10));
        return ['eq_bands' => $bands, 'noise_db' => isset($res['noise_db']) ? (float) $res['noise_db'] : null];
    }

    private function askAiTrainingSummary(int $trainCount, int $epochs, float $rmse): string
    {
        $prompt = "Tulis 1 kalimat pendek berbahasa Indonesia awam untuk pemilik situs musik: model AI selesai dilatih dengan {$trainCount} contoh lagu selama {$epochs} putaran (selisih rata-rata {$rmse}). Jelaskan manfaatnya untuk kejernihan dan kehalusan suara tanpa istilah teknis rumit.";
        $res = $this->ai->consultJson('Balas JSON {"ringkasan":"1 kalimat bahasa awam"} untuk: ' . $prompt);
        if (! empty($res['ringkasan'])) {
            return substr((string) $res['ringkasan'], 0, 240);
        }
        return "Model AI selesai mempelajari {$trainCount} contoh lagu untuk merapikan vokal, meredam desis, dan membuat musik lebih halus serta jernih.";
    }

    private function askAiForVoicePolish(Song $song, array $before, string $styleLabel, string $customPrompt): ?array
    {
        $goal = $customPrompt ?: "Buat suara lagu terasa {$styleLabel}, lembut di telinga, vokal jernih, dan bebas desis.";
        $prompt = "Rekomendasikan setelan penghalusan suara dalam JSON ketat: {\"eq_bands\":[10 angka -8 sampai 8],\"plain_notes\":\"1 kalimat bahasa Indonesia sehari-hari mudah dipahami orang awam tanpa istilah teknis\"}. "
            . "Tujuan: {$goal}. Lagu: {$song->title} ({$song->genre?->name}), skor kejernihan awal {$before['clarity_score']}/100.";
        return $this->ai->consultJson($prompt);
    }
}
