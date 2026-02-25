<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $deviceType,
        public string $deviceId,
        public string $status,
        public array $data = [],
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('devices')];
    }

    public function broadcastAs(): string
    {
        return 'device.status.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'deviceType' => $this->deviceType,
            'deviceId' => $this->deviceId,
            'status' => $this->status,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}
