<?php

namespace App\Console\Commands;

use App\Models\BiometricDevice;
use App\Models\DeviceAlert;
use App\Models\FeelbackDevice;
use App\Models\RfidDevice;
use App\Services\AlertService;
use Illuminate\Console\Command;

class CheckProlongedOfflineCommand extends Command
{
    protected $signature = 'support:check-prolonged-offline';

    protected $description = 'Crée/escalade des alertes pour les capteurs hors ligne depuis plusieurs jours (déclencheur CRM support)';

    public function handle(AlertService $alerts): int
    {
        $tiers = config('devices.prolonged_offline_days', [
            'medium' => 2,
            'high' => 7,
            'critical' => 14,
        ]);

        $this->scan(RfidDevice::query()->withoutGlobalScopes(), 'rfid', 'last_ping_at', $tiers, $alerts);
        $this->scan(BiometricDevice::query()->withoutGlobalScopes(), 'biometric', 'last_sync_at', $tiers, $alerts);
        $this->scan(FeelbackDevice::query()->withoutGlobalScopes(), 'feelback', 'last_ping_at', $tiers, $alerts);

        $this->info('Prolonged-offline check completed.');

        return self::SUCCESS;
    }

    protected function scan($query, string $kind, string $timeColumn, array $tiers, AlertService $alerts): void
    {
        $minDays = (int) ($tiers['medium'] ?? 2);
        $cutoff = now()->subDays($minDays);

        $devices = (clone $query)
            ->where('is_online', false)
            ->where(function ($q) use ($timeColumn, $cutoff) {
                $q->whereNull($timeColumn)->orWhere($timeColumn, '<', $cutoff);
            })
            ->get();

        foreach ($devices as $device) {
            $lastSeen = $device->{$timeColumn};
            $days = $lastSeen ? (int) $lastSeen->diffInDays(now()) : 9999;
            $severity = $this->severityForDays($days, $tiers);

            $label = $device->name ?? $device->serial_number ?? $device->id;
            $alert = $alerts->openOrUpdate([
                'company_id' => $device->company_id ?? null,
                'site_id' => $device->site_id ?? null,
                'device_id' => $device->id,
                'device_kind' => $kind,
                'type' => DeviceAlert::TYPE_PROLONGED_OFFLINE,
                'severity' => $severity,
                'title' => "Capteur {$kind} hors ligne depuis {$days} j : {$label}",
                'message' => "Ce capteur n'a pas été rallumé depuis {$days} jour(s). Contacter la compagnie.",
                'context' => [
                    'serial_number' => $device->serial_number ?? null,
                    'last_seen' => $lastSeen?->toISOString(),
                    'days_offline' => $days,
                    'is_witness' => (bool) ($device->is_witness ?? false),
                ],
            ]);

            // openOrUpdate ne ré-escalade pas une alerte existante : on monte la sévérité au besoin.
            if ($alert->wasRecentlyCreated === false && $this->severityRank($severity) > $this->severityRank($alert->severity)) {
                $alert->update([
                    'severity' => $severity,
                    'title' => "Capteur {$kind} hors ligne depuis {$days} j : {$label}",
                    'context' => array_merge($alert->context ?? [], ['days_offline' => $days]),
                ]);
            }
        }
    }

    protected function severityForDays(int $days, array $tiers): string
    {
        if ($days >= (int) ($tiers['critical'] ?? 14)) {
            return DeviceAlert::SEVERITY_CRITICAL;
        }
        if ($days >= (int) ($tiers['high'] ?? 7)) {
            return DeviceAlert::SEVERITY_HIGH;
        }

        return DeviceAlert::SEVERITY_MEDIUM;
    }

    protected function severityRank(string $severity): int
    {
        return match ($severity) {
            DeviceAlert::SEVERITY_CRITICAL => 4,
            DeviceAlert::SEVERITY_HIGH => 3,
            DeviceAlert::SEVERITY_MEDIUM => 2,
            default => 1,
        };
    }
}
