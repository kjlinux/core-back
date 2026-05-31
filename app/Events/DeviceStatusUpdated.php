<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Database\Eloquent\Model;
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
        public ?string $previousStatus = null,
    ) {}

    /**
     * Construit un event enrichi à partir d'un modèle device (RFID/Biometric/Feelback).
     * Hydrate name, serialNumber, companyId/Name, siteId/Name, isWitness.
     */
    public static function fromDevice(string $kind, Model $device, string $status, ?string $previousStatus = null, array $extra = []): self
    {
        $device->loadMissing(['company', 'site']);
        $data = array_merge([
            'serial_number' => $device->serial_number ?? null,
            'deviceName' => $device->name ?? null,
            'companyId' => $device->company_id ?? null,
            'companyName' => $device->company?->name,
            'siteId' => $device->site_id ?? null,
            'siteName' => $device->site?->name,
            'isWitness' => (bool) ($device->is_witness ?? false),
        ], $extra);

        return new self($kind, (string) $device->id, $status, $data, $previousStatus);
    }

    /**
     * Canaux PRIVES (cloisonnement multi-tenant) :
     *  - devices.{companyId} : membres de l'entreprise concernee ;
     *  - devices.all : flux global de supervision, reserve aux roles transverses
     *    (support IT / super_admin / technicien) qui monitorent tout le parc.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('devices.all')];

        $companyId = $this->data['companyId'] ?? null;
        if ($companyId) {
            $channels[] = new PrivateChannel('devices.'.$companyId);
        }

        return $channels;
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
            'previousStatus' => $this->previousStatus,
            'deviceName' => $this->data['deviceName'] ?? null,
            'serialNumber' => $this->data['serial_number'] ?? null,
            'companyId' => $this->data['companyId'] ?? null,
            'companyName' => $this->data['companyName'] ?? null,
            'siteId' => $this->data['siteId'] ?? null,
            'siteName' => $this->data['siteName'] ?? null,
            'isWitness' => (bool) ($this->data['isWitness'] ?? false),
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}
