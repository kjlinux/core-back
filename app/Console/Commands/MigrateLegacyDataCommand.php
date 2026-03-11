<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\BiometricDevice;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FeelbackDevice;
use App\Models\FeelbackEntry;
use App\Models\FingerprintEnrollment;
use App\Models\ReviewConfig;
use App\Models\ReviewSubmission;
use App\Models\RfidCard;
use App\Models\RfidDevice;
use App\Models\Site;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MigrateLegacyDataCommand extends Command
{
    protected $signature = 'migrate:legacy-data
                            {--dry-run : Affiche les stats sans écrire en base}
                            {--skip-attendance : Ignorer la migration des pointages}
                            {--skip-feelback : Ignorer la migration des feedbacks}';

    protected $description = 'Migre les données de l\'ancienne base SQLite vers le nouveau système multi-tenant';

    /** @var array<string, string> old_user_id => company_uuid */
    private array $companyMap = [];

    /** @var array<string, array{site_id: string, department_id: string}> old_user_id => {site_id, department_id} */
    private array $siteMap = [];

    /** @var array<string, string> old_presense_id => rfid_device_uuid */
    private array $rfidDeviceMap = [];

    /** @var array<string, string> old_finger_id => bio_device_uuid */
    private array $bioMap = [];

    /** @var array<string, array<string, string>> company_uuid => {normalized_label => employee_uuid} */
    private array $empMap = [];

    /** @var array<string, array{device_uuid: string, site_id: string}> old_feelback_id => {device_uuid, site_id} */
    private array $fbMap = [];

    private bool $dryRun = false;

    private int $orphanCount = 0;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('[DRY RUN] Aucune donnée ne sera écrite.');
        }

        if (! file_exists(config('database.connections.legacy.database'))) {
            $this->error('Fichier legacy introuvable : '.config('database.connections.legacy.database'));
            $this->info('Définissez LEGACY_DB_PATH dans votre .env pointant vers le fichier SQLite de production.');

            return self::FAILURE;
        }

        $this->info('Démarrage de la migration des données legacy...');

        if (! $this->dryRun) {
            DB::transaction(function (): void {
                $this->runMigration();
            });
        } else {
            $this->runMigration();
        }

        if ($this->orphanCount > 0) {
            $this->warn("{$this->orphanCount} enregistrements orphelins ignorés — voir storage/logs/migration-orphans.log");
        }

        $this->info('Migration terminée avec succès.');

        return self::SUCCESS;
    }

    private function runMigration(): void
    {
        $this->companyMap = $this->migrateCompaniesAndUsers();
        $this->siteMap = $this->migrateDefaultSitesAndDepartments();
        $this->rfidDeviceMap = $this->migrateRfidDevices();
        $this->bioMap = $this->migrateBiometricDevices();
        $this->empMap = $this->migrateEmployees();

        if (! $this->option('skip-attendance')) {
            $this->migrateAttendanceRecords();
        }

        if (! $this->option('skip-feelback')) {
            $this->fbMap = $this->migrateFeelbackDevices();
            $this->migrateFeelbackEntries();
            $this->migrateAvis();
        }
    }

    /** @return array<string, string> */
    private function migrateCompaniesAndUsers(): array
    {
        $map = [];
        $rows = DB::connection('legacy')->table('users')->get();

        $this->info("→ Migration de {$rows->count()} clients (users → companies + users)");

        foreach ($rows as $row) {
            if ($this->dryRun) {
                $map[$row->id] = Str::uuid()->toString();
                $this->line("  [DRY] Company: {$row->full_name} ({$row->email})");

                continue;
            }

            $company = Company::firstOrCreate(
                ['email' => $row->email],
                [
                    'name' => $row->full_name,
                    'phone' => '00000000',
                    'address' => 'À compléter',
                    'is_active' => (bool) $row->is_active,
                ]
            );

            // Remplacer le préfixe $2b$ (Node.js) par $2y$ (PHP bcrypt)
            $password = str_replace('$2b$', '$2y$', $row->password);

            User::firstOrCreate(
                ['email' => $row->email],
                [
                    'name' => $row->full_name,
                    'first_name' => $this->parseFirstName($row->full_name),
                    'last_name' => $this->parseLastName($row->full_name),
                    'password' => $password,
                    'role' => 'admin_enterprise',
                    'company_id' => $company->id,
                    'is_active' => (bool) $row->is_active,
                ]
            );

            $map[$row->id] = $company->id;
        }

        $this->info("  ✓ {$rows->count()} companies créées");

        return $map;
    }

    /** @return array<string, array{site_id: string, department_id: string}> */
    private function migrateDefaultSitesAndDepartments(): array
    {
        $map = [];

        $this->info('→ Création des sites et départements par défaut');

        foreach ($this->companyMap as $oldUserId => $companyUuid) {
            if ($this->dryRun) {
                $map[$oldUserId] = ['site_id' => Str::uuid()->toString(), 'department_id' => Str::uuid()->toString()];

                continue;
            }

            $company = Company::find($companyUuid);

            $site = Site::firstOrCreate(
                ['company_id' => $companyUuid, 'name' => $company->name],
                ['address' => 'À compléter']
            );

            $department = Department::firstOrCreate(
                ['site_id' => $site->id, 'name' => 'Général'],
                ['company_id' => $companyUuid]
            );

            $map[$oldUserId] = ['site_id' => $site->id, 'department_id' => $department->id];
        }

        $this->info('  ✓ Sites et départements créés');

        return $map;
    }

    /** @return array<string, string> */
    private function migrateRfidDevices(): array
    {
        $map = [];
        $rows = DB::connection('legacy')->table('Presense')->get();

        $this->info("→ Migration de {$rows->count()} appareils RFID (Presense → rfid_devices)");
        $created = 0;

        foreach ($rows as $row) {
            if (! isset($this->companyMap[$row->user_id])) {
                $this->logOrphan('Presense', $row->id, $row->user_id);

                continue;
            }

            $companyUuid = $this->companyMap[$row->user_id];
            $siteId = $this->siteMap[$row->user_id]['site_id'];

            if ($this->dryRun) {
                $map[$row->id] = Str::uuid()->toString();
                $this->line("  [DRY] RfidDevice: {$row->label} (topic: {$row->topic})");
                $created++;

                continue;
            }

            $device = RfidDevice::firstOrCreate(
                ['serial_number' => $row->id],
                [
                    'name' => $row->label ?? $row->topic,
                    'company_id' => $companyUuid,
                    'site_id' => $siteId,
                    'mqtt_topic' => $row->topic,
                    'is_online' => false,
                ]
            );

            $map[$row->id] = $device->id;
            $created++;
        }

        $this->info("  ✓ {$created} appareils RFID migrés");

        return $map;
    }

    /** @return array<string, string> */
    private function migrateBiometricDevices(): array
    {
        $map = [];
        $rows = DB::connection('legacy')->table('Finger')->get();

        $this->info("→ Migration de {$rows->count()} appareils biométriques (Finger → biometric_devices)");
        $created = 0;

        foreach ($rows as $row) {
            if (! isset($this->companyMap[$row->user_id])) {
                $this->logOrphan('Finger', $row->id, $row->user_id);

                continue;
            }

            $companyUuid = $this->companyMap[$row->user_id];
            $siteId = $this->siteMap[$row->user_id]['site_id'];

            if ($this->dryRun) {
                $map[$row->id] = Str::uuid()->toString();
                $this->line("  [DRY] BiometricDevice: {$row->label} (topic: {$row->topic})");
                $created++;

                continue;
            }

            $device = BiometricDevice::firstOrCreate(
                ['serial_number' => $row->id],
                [
                    'name' => $row->label ?? $row->topic,
                    'company_id' => $companyUuid,
                    'site_id' => $siteId,
                    'mqtt_topic' => $row->topic,
                    'is_online' => false,
                    'enrolled_count' => 0,
                ]
            );

            $map[$row->id] = $device->id;
            $created++;
        }

        $this->info("  ✓ {$created} appareils biométriques migrés");

        return $map;
    }

    /** @return array<string, array<string, string>> */
    private function migrateEmployees(): array
    {
        $map = [];

        $this->info('→ Migration des employés (PresenceCartes + FinterIdentification → employees)');

        // Collecter depuis PresenceCartes
        $cards = DB::connection('legacy')->table('PresenceCartes')->get();
        // Collecter depuis FinterIdentification
        $fingers = DB::connection('legacy')->table('FinterIdentification')->get();

        // Construire map: company_uuid => [normalized_label => {source_user_id, created_at, has_bio}]
        $employeeData = [];

        foreach ($cards as $card) {
            if (! isset($this->companyMap[$card->user_id])) {
                $this->logOrphan('PresenceCartes', $card->id, $card->user_id);

                continue;
            }
            $companyUuid = $this->companyMap[$card->user_id];
            $key = $this->normalizeLabel($card->label);
            if (! isset($employeeData[$companyUuid][$key])) {
                $employeeData[$companyUuid][$key] = [
                    'label' => $card->label,
                    'created_at' => $card->created_at,
                    'has_bio' => false,
                    'old_user_id' => $card->user_id,
                ];
            }
        }

        foreach ($fingers as $finger) {
            if (! isset($this->companyMap[$finger->user_id])) {
                $this->logOrphan('FinterIdentification', $finger->id, $finger->user_id);

                continue;
            }
            $companyUuid = $this->companyMap[$finger->user_id];
            $key = $this->normalizeLabel($finger->label);
            if (! isset($employeeData[$companyUuid][$key])) {
                $employeeData[$companyUuid][$key] = [
                    'label' => $finger->label,
                    'created_at' => $finger->created_at,
                    'has_bio' => true,
                    'old_user_id' => $finger->user_id,
                ];
            } else {
                $employeeData[$companyUuid][$key]['has_bio'] = true;
            }
        }

        $totalEmployees = 0;

        foreach ($employeeData as $companyUuid => $employees) {
            $map[$companyUuid] = [];
            foreach ($employees as $normalizedLabel => $data) {
                $oldUserId = $data['old_user_id'];
                $siteId = $this->siteMap[$oldUserId]['site_id'];
                $deptId = $this->siteMap[$oldUserId]['department_id'];

                if ($this->dryRun) {
                    $map[$companyUuid][$normalizedLabel] = Str::uuid()->toString();
                    $this->line("  [DRY] Employee: {$data['label']}");
                    $totalEmployees++;

                    continue;
                }

                $firstName = $this->parseFirstName($data['label']);
                $lastName = $this->parseLastName($data['label']);
                $slug = Str::slug($data['label']);
                $empNumber = 'EMP-'.strtoupper(substr(Str::uuid()->toString(), 0, 8));

                $employee = Employee::firstOrCreate(
                    ['company_id' => $companyUuid, 'email' => "emp_{$slug}@migration.local"],
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => '00000000',
                        'position' => 'Employé',
                        'employee_number' => $empNumber,
                        'site_id' => $siteId,
                        'department_id' => $deptId,
                        'hire_date' => substr($data['created_at'], 0, 10),
                        'biometric_enrolled' => $data['has_bio'],
                        'is_active' => true,
                    ]
                );

                $map[$companyUuid][$normalizedLabel] = $employee->id;
                $totalEmployees++;
            }
        }

        $this->info("  ✓ {$totalEmployees} employés migrés");

        // Migrer les cartes RFID
        $this->migrateRfidCards($map);

        // Migrer les enrollments biométriques
        $this->migrateFingerprintEnrollments($map);

        return $map;
    }

    /** @param array<string, array<string, string>> $empMap */
    private function migrateRfidCards(array $empMap): void
    {
        $cards = DB::connection('legacy')->table('PresenceCartes')->get();
        $created = 0;

        foreach ($cards as $card) {
            if (! isset($this->companyMap[$card->user_id])) {
                continue;
            }

            $companyUuid = $this->companyMap[$card->user_id];
            $normalizedLabel = $this->normalizeLabel($card->label);
            $employeeId = $empMap[$companyUuid][$normalizedLabel] ?? null;

            if (! $employeeId) {
                $this->logOrphan('PresenceCartes.card', $card->id, $card->user_id);

                continue;
            }

            if ($this->dryRun) {
                $this->line("  [DRY] RfidCard UID: {$card->uid} → {$card->label}");
                $created++;

                continue;
            }

            // uid est unique globalement — on skip si la carte existe déjà (partagée entre clients dans l'ancienne DB)
            RfidCard::firstOrCreate(
                ['uid' => $card->uid],
                [
                    'company_id' => $companyUuid,
                    'employee_id' => $employeeId,
                    'status' => $card->status ? 'active' : 'inactive',
                    'assigned_at' => $card->created_at,
                ]
            );

            $created++;
        }

        $this->info("  ✓ {$created} cartes RFID migrées");
    }

    /** @param array<string, array<string, string>> $empMap */
    private function migrateFingerprintEnrollments(array $empMap): void
    {
        // NOTE: locket_id dans FinterIdentification référence Finger.id (bug schema old)
        $fingers = DB::connection('legacy')->table('FinterIdentification')->get();
        $created = 0;

        foreach ($fingers as $finger) {
            if (! isset($this->companyMap[$finger->user_id])) {
                continue;
            }

            $companyUuid = $this->companyMap[$finger->user_id];
            $normalizedLabel = $this->normalizeLabel($finger->label);
            $employeeId = $empMap[$companyUuid][$normalizedLabel] ?? null;
            // locket_id référence Finger.id
            $deviceId = $this->bioMap[$finger->locket_id] ?? null;

            if (! $employeeId || ! $deviceId) {
                $this->logOrphan('FinterIdentification', $finger->id, $finger->user_id);

                continue;
            }

            if ($this->dryRun) {
                $this->line("  [DRY] FingerprintEnrollment: uid={$finger->uid} → {$finger->label}");
                $created++;

                continue;
            }

            FingerprintEnrollment::firstOrCreate(
                ['employee_id' => $employeeId, 'template_hash' => (string) $finger->uid],
                [
                    'device_id' => $deviceId,
                    'status' => 'enrolled',
                    'enrolled_at' => $finger->created_at,
                ]
            );

            $created++;
        }

        $this->info("  ✓ {$created} enrollments biométriques migrés");

        // Mettre à jour enrolled_count sur chaque BiometricDevice
        if (! $this->dryRun) {
            foreach ($this->bioMap as $deviceUuid) {
                $count = FingerprintEnrollment::where('device_id', $deviceUuid)->count();
                BiometricDevice::where('id', $deviceUuid)->update(['enrolled_count' => $count]);
            }
        }
    }

    private function migrateAttendanceRecords(): void
    {
        $this->info('→ Migration des pointages RFID (PresenseEvents → attendance_records)');
        $this->migrateRfidAttendance();

        $this->info('→ Migration des pointages biométriques (FingerEvents → attendance_records)');
        $this->migrateBiometricAttendance();
    }

    private function migrateRfidAttendance(): void
    {
        $events = DB::connection('legacy')
            ->table('PresenseEvents')
            ->where('event_type', 'GRANTED')
            ->orderBy('event_date')
            ->get();

        $created = $this->processAttendanceEvents($events, 'rfid', function (string $uid, ?string $userId): ?string {
            if ($this->dryRun) {
                return Str::uuid()->toString();
            }

            $card = RfidCard::where('uid', $uid)->first();

            return $card?->employee_id;
        });

        $this->info("  ✓ {$created} enregistrements de pointage RFID créés");
    }

    private function migrateBiometricAttendance(): void
    {
        $events = DB::connection('legacy')
            ->table('FingerEvents')
            ->where('event_type', 'GRANTED')
            ->orderBy('event_date')
            ->get();

        $created = $this->processAttendanceEvents($events, 'biometric', function (string $uid, ?string $userId): ?string {
            if ($this->dryRun) {
                return Str::uuid()->toString();
            }

            $enrollment = FingerprintEnrollment::where('template_hash', $uid)->first();

            return $enrollment?->employee_id;
        });

        $this->info("  ✓ {$created} enregistrements de pointage biométrique créés");
    }

    /**
     * @param  callable(string $uid, ?string $userId): ?string  $resolveEmployee
     */
    private function processAttendanceEvents(Collection $events, string $source, callable $resolveEmployee): int
    {
        $created = 0;

        // Grouper par uid + date
        $grouped = $events->groupBy(function ($e) {
            return $e->uid.'|'.substr($e->event_date, 0, 10);
        });

        foreach ($grouped as $key => $dayEvents) {
            [$uid, $date] = explode('|', $key, 2);

            $deduped = $this->deduplicateByWindow($dayEvents, 30);

            $employeeId = $resolveEmployee($uid, $dayEvents->first()->user_id ?? null);

            if (! $employeeId) {
                $this->logOrphan('AttendanceEvent', $uid, $dayEvents->first()->user_id ?? 'unknown');

                continue;
            }

            if ($this->dryRun) {
                $created++;

                continue;
            }

            $existingRecord = AttendanceRecord::where('employee_id', $employeeId)
                ->where('date', $date)
                ->first();

            if ($existingRecord) {
                continue;
            }

            AttendanceRecord::create([
                'employee_id' => $employeeId,
                'date' => $date,
                'entry_time' => $deduped->first()->event_date,
                'exit_time' => $deduped->count() > 1 ? $deduped->last()->event_date : null,
                'status' => 'present',
                'source' => $source,
                'is_double_badge' => false,
            ]);

            $created++;
        }

        return $created;
    }

    private function deduplicateByWindow(Collection $events, int $windowSeconds): Collection
    {
        $deduped = collect();
        $lastTimestamp = null;

        foreach ($events->sortBy('event_date') as $event) {
            $timestamp = strtotime($event->event_date);

            if ($lastTimestamp === null || ($timestamp - $lastTimestamp) > $windowSeconds) {
                $deduped->push($event);
                $lastTimestamp = $timestamp;
            }
        }

        return $deduped;
    }

    /** @return array<string, array{device_uuid: string, site_id: string}> */
    private function migrateFeelbackDevices(): array
    {
        $map = [];
        $rows = DB::connection('legacy')->table('feelbacks')->get();

        $this->info("→ Migration de {$rows->count()} appareils feelback");
        $created = 0;

        foreach ($rows as $row) {
            if (! isset($this->companyMap[$row->user_id])) {
                $this->logOrphan('feelbacks', $row->id, $row->user_id);

                continue;
            }

            $companyUuid = $this->companyMap[$row->user_id];
            $siteId = $this->siteMap[$row->user_id]['site_id'];

            if ($this->dryRun) {
                $map[$row->id] = ['device_uuid' => Str::uuid()->toString(), 'site_id' => $siteId];
                $created++;

                continue;
            }

            $device = FeelbackDevice::firstOrCreate(
                ['serial_number' => $row->id],
                [
                    'company_id' => $companyUuid,
                    'site_id' => $siteId,
                    'mqtt_topic' => $row->topic,
                    'is_online' => false,
                ]
            );

            $map[$row->id] = ['device_uuid' => $device->id, 'site_id' => $siteId];
            $created++;
        }

        $this->info("  ✓ {$created} appareils feelback migrés");

        return $map;
    }

    private function migrateFeelbackEntries(): void
    {
        $rows = DB::connection('legacy')->table('feelbacks')->get();
        $created = 0;

        $this->info('→ Migration des entrées feelback (compteurs bad/good/middle → feelback_entries)');

        foreach ($rows as $row) {
            if (! isset($this->fbMap[$row->id])) {
                continue;
            }

            $deviceUuid = $this->fbMap[$row->id]['device_uuid'];
            $siteId = $this->fbMap[$row->id]['site_id'];

            $levels = [
                'bon' => (int) $row->good,
                'neutre' => (int) $row->middle,
                'mauvais' => (int) $row->bad,
            ];

            foreach ($levels as $level => $count) {
                for ($i = 0; $i < $count; $i++) {
                    if ($this->dryRun) {
                        $created++;

                        continue;
                    }

                    FeelbackEntry::create([
                        'device_id' => $deviceUuid,
                        'level' => $level,
                        'site_id' => $siteId,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);

                    $created++;
                }
            }
        }

        $this->info("  ✓ {$created} entrées feelback créées");
    }

    private function migrateAvis(): void
    {
        $rows = DB::connection('legacy')->table('avis')->get();

        $this->info("→ Migration de {$rows->count()} avis textuels (avis → review_submissions)");
        $created = 0;

        // Mapper feelback_id → company via l'ancienne table feelbacks
        $feelbackUserMap = DB::connection('legacy')
            ->table('feelbacks')
            ->pluck('user_id', 'id')
            ->toArray();

        foreach ($rows as $row) {
            $oldUserId = $feelbackUserMap[$row->feelback_id] ?? null;

            if (! $oldUserId || ! isset($this->companyMap[$oldUserId])) {
                $this->logOrphan('avis', $row->id, $row->feelback_id);

                continue;
            }

            $companyUuid = $this->companyMap[$oldUserId];

            if ($this->dryRun) {
                $this->line("  [DRY] ReviewSubmission: {$row->identite} — ".Str::limit($row->avis, 50));
                $created++;

                continue;
            }

            // Créer un ReviewConfig minimal si absent pour cette company
            $reviewConfig = ReviewConfig::firstOrCreate(
                ['company_id' => $companyUuid],
                [
                    'token' => Str::random(32),
                    'is_active' => true,
                ]
            );

            ReviewSubmission::create([
                'review_config_id' => $reviewConfig->id,
                'recommendations' => $row->identite.': '.$row->avis,
                'channel' => 'legacy_migration',
            ]);

            $created++;
        }

        $this->info("  ✓ {$created} avis migrés en review_submissions");
    }

    private function parseFirstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));

        return array_pop($parts) ?: $fullName;
    }

    private function parseLastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        array_pop($parts);

        return implode(' ', $parts) ?: $fullName;
    }

    private function normalizeLabel(string $label): string
    {
        return strtolower(trim($label));
    }

    private function logOrphan(string $table, string $id, string $userId): void
    {
        $this->orphanCount++;
        $message = "[ORPHAN] {$table} id={$id} user_id={$userId} — user_id introuvable dans companyMap";
        Log::channel('daily')->warning($message, ['context' => 'legacy-migration']);

        $logFile = storage_path('logs/migration-orphans.log');
        file_put_contents($logFile, date('Y-m-d H:i:s').' '.$message.PHP_EOL, FILE_APPEND);
    }
}
