<?php

namespace App\Console\Commands;

use App\Models\BiometricAuditLog;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\FingerprintEnrollment;
use App\Services\MqttService;
use Illuminate\Console\Command;

class BiometricResetDuplicates extends Command
{
    protected $signature = 'biometric:reset-duplicates
                            {--dry-run : Affiche uniquement les doublons detectes}
                            {--force : Execute le nettoyage sans confirmation}
                            {--skip-wipe : Ne pas envoyer CMD_DELETE_ALL aux terminaux}';

    protected $description = 'Invalide les enrollments avec template_hash dupliques par terminal et envoie CMD_DELETE_ALL';

    public function handle(MqttService $mqtt): int
    {
        $duplicates = $this->findDuplicates();

        if ($duplicates->isEmpty()) {
            $this->info('Aucun doublon detecte.');

            return self::SUCCESS;
        }

        $this->warn("{$duplicates->count()} enrollments dupliques detectes :");
        $this->table(
            ['Device', 'Template', 'Employee', 'Status', 'Created'],
            $duplicates->map(fn (FingerprintEnrollment $e) => [
                $e->device?->serial_number ?? $e->device_id,
                $e->template_hash,
                $e->employee?->full_name ?? $e->employee_id,
                $e->status,
                (string) $e->created_at,
            ])->all()
        );

        if ($this->option('dry-run')) {
            $this->info('Mode dry-run : aucune modification.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Passer tous ces enrollments en status=failed ?')) {
            $this->info('Abandon.');

            return self::SUCCESS;
        }

        $deviceIds = $duplicates->pluck('device_id')->unique();

        foreach ($duplicates as $enrollment) {
            $this->invalidateEnrollment($enrollment);
        }

        $this->info("{$duplicates->count()} enrollments passes en failed.");

        if ($this->option('skip-wipe')) {
            $this->warn('--skip-wipe : aucun CMD_DELETE_ALL envoye. Pensez a reenroler manuellement.');

            return self::SUCCESS;
        }

        $this->sendWipeToDevices($deviceIds->all(), $mqtt);

        return self::SUCCESS;
    }

    /**
     * Retourne tous les enrollments enrolled dont le couple (device_id, template_hash)
     * apparait plus d'une fois.
     */
    private function findDuplicates()
    {
        $duplicateKeys = FingerprintEnrollment::query()
            ->where('status', 'enrolled')
            ->selectRaw('device_id, template_hash, COUNT(*) as c')
            ->groupBy('device_id', 'template_hash')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateKeys->isEmpty()) {
            return collect();
        }

        $query = FingerprintEnrollment::query()->with(['device', 'employee'])->where('status', 'enrolled');

        $query->where(function ($outer) use ($duplicateKeys) {
            foreach ($duplicateKeys as $key) {
                $outer->orWhere(function ($q) use ($key) {
                    $q->where('device_id', $key->device_id)
                        ->where('template_hash', $key->template_hash);
                });
            }
        });

        return $query->orderBy('device_id')->orderBy('template_hash')->orderBy('created_at')->get();
    }

    private function invalidateEnrollment(FingerprintEnrollment $enrollment): void
    {
        $employee = Employee::find($enrollment->employee_id);

        $enrollment->update(['status' => 'failed']);

        if ($enrollment->device) {
            $enrollment->device->decrement('enrolled_count');
        }

        if ($employee) {
            $stillEnrolled = FingerprintEnrollment::where('employee_id', $employee->id)
                ->where('status', 'enrolled')
                ->exists();
            $employee->update(['biometric_enrolled' => $stillEnrolled]);
        }

        BiometricAuditLog::create([
            'user_id' => $enrollment->employee_id,
            'user_name' => $employee?->full_name ?? $enrollment->employee_id,
            'action' => 'enrollment_invalidated_duplicate',
            'target' => $employee?->full_name ?? $enrollment->employee_id,
            'details' => sprintf(
                'Enrollment invalide (doublon template_hash=%s sur device=%s)',
                $enrollment->template_hash,
                $enrollment->device?->serial_number ?? $enrollment->device_id
            ),
        ]);
    }

    /**
     * @param  array<int, string>  $deviceIds
     */
    private function sendWipeToDevices(array $deviceIds, MqttService $mqtt): void
    {
        $code = config('mqtt.command_codes.biometric.DELETE_ALL');
        $prefix = config('mqtt.topics.biometric');

        foreach (BiometricDevice::whereIn('id', $deviceIds)->get() as $device) {
            if (! $device->mqtt_topic) {
                $this->warn("Device {$device->serial_number} sans mqtt_topic -- skip wipe.");

                continue;
            }

            $responseTopic = $mqtt->getResponseTopic($device->mqtt_topic);
            $payload = json_encode([
                'command' => $code,
                'device_id' => $device->id,
                'device_type' => 'biometric',
                'timestamp' => now()->toISOString(),
            ]);

            try {
                $mqtt->publish($responseTopic, $payload);
                $this->info("CMD_DELETE_ALL publie sur {$responseTopic}");

                BiometricAuditLog::create([
                    'user_id' => null,
                    'user_name' => 'system',
                    'action' => 'device_wipe_sent',
                    'target' => $device->serial_number,
                    'details' => 'CMD_DELETE_ALL envoye apres detection doublons',
                ]);
            } catch (\Exception $e) {
                $this->error("Echec publish sur {$responseTopic}: {$e->getMessage()}");
            }
        }
    }
}
