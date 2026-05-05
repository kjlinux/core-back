<?php

namespace App\Events;

use App\Models\DeviceAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceAlertResolved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DeviceAlert $alert) {}

    public function broadcastOn(): array
    {
        return [new Channel('support')];
    }

    public function broadcastAs(): string
    {
        return 'alert.resolved';
    }

    public function broadcastWith(): array
    {
        return [
            'alertId' => $this->alert->id,
            'status' => $this->alert->status,
            'resolvedAt' => $this->alert->resolved_at?->toISOString(),
        ];
    }
}
