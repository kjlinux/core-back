<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CardScanned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $uid,
        public string $deviceId,
        public string $companyId,
    ) {}

    /**
     * Canal prive scope par entreprise : seul un membre de l'entreprise (ou un
     * super_admin/technicien) recoit l'UID scanne, pas tous les clients connectes.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('cards.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'card.scanned';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'uid' => $this->uid,
            'deviceId' => $this->deviceId,
        ];
    }
}
