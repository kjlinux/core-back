<?php

namespace App\Console\Commands;

use App\Events\SystemHealthChanged;
use App\Models\BiometricDevice;
use App\Models\DeviceAlert;
use App\Models\FeelbackDevice;
use App\Models\RfidDevice;
use App\Services\AlertService;
use App\Services\HealthService;
use Illuminate\Console\Command;

class CheckDeviceHealthCommand extends Command
{
    protected $signature = 'health:check-devices {--threshold=5 : Minutes before marking offline}';
    protected $description = 'Check device heartbeats, mark offline ones, and emit alerts';

    public function handle(AlertService $alerts, HealthService $health): int
    {
        $threshold = (int) $this->option('threshold');
        $cutoff = now()->subMinutes($threshold);

        $this->checkDeviceTable(RfidDevice::query()->withoutGlobalScopes(), 'rfid', 'last_ping_at', $cutoff, $alerts);
        $this->checkDeviceTable(BiometricDevice::query()->withoutGlobalScopes(), 'biometric', 'last_sync_at', $cutoff, $alerts);
        $this->checkDeviceTable(FeelbackDevice::query()->withoutGlobalScopes(), 'feelback', 'last_ping_at', $cutoff, $alerts);

        $report = $health->snapshot();
        event(new SystemHealthChanged($report));

        foreach ($report['components'] ?? [] as $name => $component) {
            if ($name === 'listeners') {
                foreach ($component as $subName => $subComponent) {
                    $this->processComponent("listeners.{$subName}", $subComponent, $alerts);
                }
                continue;
            }
            $this->processComponent($name, $component, $alerts);
        }

        $this->info('Health check completed.');
        return self::SUCCESS;
    }

    protected function checkDeviceTable($query, string $kind, string $timeColumn, $cutoff, AlertService $alerts): void
    {
        $devices = (clone $query)
            ->where('is_online', true)
            ->where(function ($q) use ($timeColumn, $cutoff) {
                $q->whereNull($timeColumn)->orWhere($timeColumn, '<', $cutoff);
            })
            ->get();

        foreach ($devices as $device) {
            $device->is_online = false;
            $device->save();

            $alerts->openOrUpdate([
                'company_id' => $device->company_id ?? null,
                'site_id' => $device->site_id ?? null,
                'device_id' => $device->id,
                'device_kind' => $kind,
                'type' => DeviceAlert::TYPE_OFFLINE_THRESHOLD,
                'severity' => $device->is_witness ? DeviceAlert::SEVERITY_CRITICAL : DeviceAlert::SEVERITY_HIGH,
                'title' => "Capteur {$kind} hors ligne : " . ($device->name ?? $device->serial_number ?? $device->id),
                'message' => "Aucun signal depuis plus que le seuil. Dernier contact: " . ($device->{$timeColumn}?->diffForHumans() ?? 'inconnu'),
                'context' => [
                    'serial_number' => $device->serial_number ?? null,
                    'last_seen' => $device->{$timeColumn}?->toISOString(),
                    'is_witness' => (bool) ($device->is_witness ?? false),
                ],
            ]);
        }
    }

    protected function processComponent(string $name, array $component, AlertService $alerts): void
    {
        if (($component['status'] ?? 'ok') !== 'ok') {
            $alerts->openOrUpdate([
                'device_kind' => 'system',
                'device_id' => null,
                'type' => $this->mapComponentToType($name),
                'severity' => DeviceAlert::SEVERITY_HIGH,
                'title' => "Composant système dégradé : {$name}",
                'message' => $component['message'] ?? null,
                'context' => $component,
            ]);
        } else {
            \App\Models\DeviceAlert::query()
                ->where('device_kind', 'system')
                ->where('type', $this->mapComponentToType($name))
                ->whereIn('status', [DeviceAlert::STATUS_OPEN, DeviceAlert::STATUS_ACKNOWLEDGED])
                ->get()
                ->each(fn ($a) => $alerts->resolve($a));
        }
    }

    protected function mapComponentToType(string $name): string
    {
        return match ($name) {
            'mqtt' => DeviceAlert::TYPE_BROKER_DOWN,
            'reverb' => DeviceAlert::TYPE_REVERB_DOWN,
            'listeners.rfid', 'listeners.biometric', 'listeners.feelback' => DeviceAlert::TYPE_LISTENER_DOWN,
            default => DeviceAlert::TYPE_API_ERROR,
        };
    }
}
