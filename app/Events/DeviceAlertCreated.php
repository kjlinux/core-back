<?php

namespace App\Events;

use App\Models\DeviceAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceAlertCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DeviceAlert $alert) {}

    public function broadcastOn(): array
    {
        return [new Channel('support')];
    }

    public function broadcastAs(): string
    {
        return 'alert.created';
    }

    public function broadcastWith(): array
    {
        return [
            'alert' => [
                'id' => $this->alert->id,
                'company_id' => $this->alert->company_id,
                'site_id' => $this->alert->site_id,
                'device_id' => $this->alert->device_id,
                'device_kind' => $this->alert->device_kind,
                'type' => $this->alert->type,
                'severity' => $this->alert->severity,
                'title' => $this->alert->title,
                'message' => $this->alert->message,
                'context' => $this->alert->context,
                'status' => $this->alert->status,
                'acknowledged_by' => $this->alert->acknowledged_by,
                'acknowledged_at' => $this->alert->acknowledged_at?->toISOString(),
                'resolved_at' => $this->alert->resolved_at?->toISOString(),
                'created_at' => $this->alert->created_at?->toISOString(),
            ],
        ];
    }
}
