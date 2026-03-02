<?php

namespace App\Events;

use App\Models\FeelbackEntry;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeelbackReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FeelbackEntry $entry)
    {
        $this->entry->load('site', 'device');
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('feelback')];
    }

    public function broadcastAs(): string
    {
        return 'feelback.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => (string) $this->entry->id,
            'deviceId' => (string) $this->entry->device_id,
            'level' => $this->entry->level,
            'siteName' => $this->entry->site->name ?? '',
            'timestamp' => $this->entry->created_at->toISOString(),
        ];
    }
}
