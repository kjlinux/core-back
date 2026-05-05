<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemHealthChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $health) {}

    public function broadcastOn(): array
    {
        return [new Channel('support')];
    }

    public function broadcastAs(): string
    {
        return 'health.changed';
    }

    public function broadcastWith(): array
    {
        return $this->health + ['timestamp' => now()->toISOString()];
    }
}
