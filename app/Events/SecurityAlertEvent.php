<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SecurityAlertEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public string $message,
        public ?string $ip = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.alerts')];
    }

    public function broadcastAs(): string
    {
        return 'security.alert';
    }

    public function broadcastWith(): array
    {
        return [
            'action'  => $this->action,
            'message' => $this->message,
            'ip'      => $this->ip,
            'time'    => now()->format('H:i:s'),
        ];
    }
}
