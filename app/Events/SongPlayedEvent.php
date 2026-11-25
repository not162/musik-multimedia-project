<?php

namespace App\Events;

use App\Models\Song;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SongPlayedEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public array $payload;

    public function __construct(Song $song, ?string $listenerName = null)
    {
        $song->loadMissing('artist:id,name');
        $this->payload = [
            'song_id'       => $song->id,
            'title'         => $song->title,
            'artist'        => $song->artist?->name ?? 'Artis',
            'play_count'    => (int) $song->play_count,
            'listener_name' => $listenerName ?: 'Pendengar Publik',
            'played_at'     => now()->format('H:i:s'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('musikku.live')];
    }

    public function broadcastAs(): string
    {
        return 'song.played';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
