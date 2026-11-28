<?php

namespace App\Events;

use App\Models\StudioRequest;
use App\Services\AudioTrainingService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Menghubungkan Fitur Studio Musik (User) dan AI Studio (Admin) secara real-time
 * tanpa membocorkan detail AI/API Admin kepada role User.
 */
class StudioRequestUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public array $payload;

    public function __construct(StudioRequest $studioRequest)
    {
        $studioRequest->loadMissing(['user:id,name', 'song:id,title']);
        $styleInfo = AudioTrainingService::VOICE_PRESETS[$studioRequest->desired_style] ?? null;

        $this->payload = [
            'id'          => $studioRequest->id,
            'user_id'     => $studioRequest->user_id,
            'user_name'   => $studioRequest->user?->name ?? 'Anggota',
            'song_id'     => $studioRequest->song_id,
            'song_title'  => $studioRequest->song?->title ?? 'Lagu',
            'style_key'   => $studioRequest->desired_style,
            'style_label' => $styleInfo['label'] ?? 'Halus & Jernih',
            'status'      => $studioRequest->status,
            'note'        => $studioRequest->note,
            'admin_reply' => $studioRequest->admin_reply,
            'updated_at'  => now()->format('H:i:s'),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.alerts'),
            new PrivateChannel('user.' . $this->payload['user_id']),
        ];
    }

    public function broadcastAs(): string
    {
        return 'studio.request';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
