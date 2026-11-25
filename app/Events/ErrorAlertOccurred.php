<?php

namespace App\Events;

use App\Models\ErrorLog;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 9: Real-time Error Alert broadcast ONLY to Admin Role.
 */
class ErrorAlertOccurred implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public array $payload;

    public function __construct(ErrorLog $errorLog)
    {
        $this->payload = [
            'id'         => $errorLog->id,
            'level'      => $errorLog->level,
            'channel'    => $errorLog->channel,
            'message'    => $errorLog->message,
            'url'        => $errorLog->url,
            'method'     => $errorLog->method,
            'created_at' => $errorLog->created_at?->format('H:i:s') ?? now()->format('H:i:s'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.alerts')];
    }

    public function broadcastAs(): string
    {
        return 'error.alert';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
