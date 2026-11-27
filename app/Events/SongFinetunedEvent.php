<?php

namespace App\Events;

use App\Models\Song;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts in real-time when Admin fine-tunes a song (AI or DSP),
 * updating Public views, User players, and API subscribers simultaneously.
 */
class SongFinetunedEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public array $payload;

    public function __construct(Song $song, string $source = 'ai')
    {
        $song->loadMissing(['artist:id,name', 'recordLabel:id,name,slug']);

        $this->payload = [
            'song_id'                => $song->id,
            'title'                  => $song->title,
            'artist'                 => $song->artist?->name ?? 'Artis',
            'label'                  => $song->recordLabel?->name,
            'media_format'           => $song->media_format ?: 'mp3',
            'target_sample_rate_khz' => $song->target_sample_rate_khz,
            'has_hires_master'       => ! empty($song->hires_file_path),
            'is_finetuned'           => (bool) $song->is_ai_finetuned,
            'is_voice_smoothed'      => (bool) $song->is_voice_smoothed,
            'voice_preset'           => $song->voice_preset,
            'clarity_score'          => $song->voice_clarity_score,
            'smoothness_score'       => $song->voice_smoothness_score,
            'mood'                   => $song->ai_mood,
            'bpm'                    => $song->ai_tempo_bpm,
            'direction'              => $song->ai_direction,
            'eq_bands'               => $song->ai_eq_bands,
            'preamp_db'              => (float) $song->ai_preamp_db,
            'pitch_rate'             => (float) ($song->ai_pitch_rate ?: 1.0),
            'notes'                  => $song->ai_mastering_notes,
            'source'                 => $source,
            'updated_at'             => now()->format('H:i:s'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('musikku.live')];
    }

    public function broadcastAs(): string
    {
        return 'song.finetuned';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
