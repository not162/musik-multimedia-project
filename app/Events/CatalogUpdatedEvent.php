<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CatalogUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public string $type,
        public int $id,
        public string $title,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('musikku.live')];
    }

    public function broadcastAs(): string
    {
        return 'catalog.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'type'   => $this->type,
            'id'     => $this->id,
            'title'  => $this->title,
            'time'   => now()->format('H:i:s'),
        ];
    }
}
