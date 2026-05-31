<?php

namespace App\Console\Commands;

use App\Mail\EmployeeCreatedMail;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BackfillEmployeeUsers extends Command
{
    protected $signature = 'employees:backfill-users
        {--dry-run : Simule sans rien persister}
        {--send-mail : Envoie l\'email de bienvenue avec les identifiants aux comptes créés}
        {--export= : Chemin d\'un fichier CSV où exporter les comptes créés et leurs mots de passe}';

    protected $description = 'Crée les comptes utilisateurs manquants pour les employés dont l\'email est présent, unique et libre. Les employés sans email exploitable (absent, partagé ou en doublon) sont volontairement ignorés (aucun compte créé).';

    /**
     * Emails déjà réservés (clé = email en minuscule), alimenté au fil de l'exécution
     * pour garantir l'unicité des emails utilisés.
     *
     * @var array<string, true>
     */
    private array $reserved = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $missing = Employee::whereDoesntHave('user')->with('company')->orderBy('company_id')->get();

        if ($missing->isEmpty()) {
            $this->info('Tous les employés ont déjà un compte utilisateur.');

            return self::SUCCESS;
        }

        $this->reserved = $this->buildReservedEmails();
        $frequency = $this->buildEmployeeEmailFrequency();

        $clean = collect();
        $skipped = collect();

        foreach ($missing as $employee) {
            if ($this->isClean($employee, $frequency)) {
                $clean->push($employee);
            } else {
                $skipped->push($employee);
            }
        }

        $this->info("Employés sans compte utilisateur : {$missing->count()}");
        $this->table(
            ['Catégorie', 'Nombre'],
            [
                ['Comptes à créer (email présent, unique et libre)', $clean->count()],
                ['Ignorés (email absent, partagé ou en doublon)', $skipped->count()],
            ]
        );

        if ($dryRun) {
            $this->warn('Mode --dry-run : aucune écriture en base.');
        }

        $createdRows = [];

        foreach ($clean as $employee) {
            $email = trim((string) $employee->email);
            $createdRows[] = $this->createAccount($employee, $email, $dryRun);
        }

        if ($skipped->isNotEmpty()) {
            $this->warn("  {$skipped->count()} employé(s) sans email exploitable : aucun compte créé (volontaire).");
            foreach ($skipped as $employee) {
                $label = trim($employee->first_name.' '.$employee->last_name);
                $reason = trim((string) $employee->email) === '' ? 'email absent' : 'email partagé ou en doublon';
                $this->line("  [ignoré] {$label} ({$reason})");
            }
        }

        $createdRows = array_values(array_filter($createdRows));

        if ($this->option('send-mail') && ! $dryRun) {
            $this->sendWelcomeMails($createdRows);
        }

        if ($this->option('export')) {
            $this->exportCredentials($createdRows, $dryRun);
        }

        $this->info('Terminé. Comptes '.($dryRun ? 'à créer' : 'créés').' : '.count($createdRows));

        return self::SUCCESS;
    }

    /**
     * Un cas est « propre » si l'email est présent, libre côté users et non partagé entre employés.
     *
     * @param  array<string, int>  $frequency
     */
    private function isClean(Employee $employee, array $frequency): bool
    {
        $key = Str::lower(trim((string) $employee->email));

        if ($key === '') {
            return false;
        }

        return ! isset($this->reserved[$key]) && ($frequency[$key] ?? 0) <= 1;
    }

    /**
     * Crée le compte (ou le simule) et renvoie la ligne récapitulative.
     *
     * @return array{employee: Employee, email: string, password: string}|null
     */
    private function createAccount(Employee $employee, string $email, bool $dryRun): ?array
    {
        $plainPassword = Str::random(12);

        if (! $dryRun) {
            User::create([
                'name' => $employee->first_name.' '.$employee->last_name,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $email,
                'phone' => $employee->phone,
                'password' => Hash::make($plainPassword),
                'role' => 'employe',
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'is_active' => true,
            ]);
        }

        $this->reserved[Str::lower($email)] = true;

        $label = $employee->first_name.' '.$employee->last_name;
        $this->line("  [créé] {$label} : {$email}");

        return [
            'employee' => $employee,
            'email' => $email,
            'password' => $plainPassword,
        ];
    }

    /**
     * Emails déjà pris par un compte utilisateur (clé minuscule).
     *
     * @return array<string, true>
     */
    private function buildReservedEmails(): array
    {
        $reserved = [];

        foreach (User::pluck('email') as $email) {
            $key = Str::lower(trim((string) $email));
            if ($key !== '') {
                $reserved[$key] = true;
            }
        }

        return $reserved;
    }

    /**
     * Fréquence de chaque email (minuscule) parmi l'ensemble des employés.
     *
     * @return array<string, int>
     */
    private function buildEmployeeEmailFrequency(): array
    {
        $frequency = [];

        foreach (Employee::pluck('email') as $email) {
            $key = Str::lower(trim((string) $email));
            if ($key !== '') {
                $frequency[$key] = ($frequency[$key] ?? 0) + 1;
            }
        }

        return $frequency;
    }

    /**
     * @param  array<int, array{employee: Employee, email: string, password: string}>  $rows
     */
    private function sendWelcomeMails(array $rows): void
    {
        $sent = 0;

        foreach ($rows as $row) {
            $user = User::where('employee_id', $row['employee']->id)->first();
            if (! $user) {
                continue;
            }

            try {
                Mail::to($row['email'])->send(new EmployeeCreatedMail($row['employee'], $user, $row['password']));
                $sent++;
            } catch (\Exception $e) {
                Log::error('EmployeeCreatedMail (backfill) failed for '.$row['email'].': '.$e->getMessage());
                $this->warn("  Email non envoyé à {$row['email']} : {$e->getMessage()}");
            }
        }

        $this->info("Emails de bienvenue envoyés : {$sent}");
    }

    /**
     * @param  array<int, array{employee: Employee, email: string, password: string}>  $rows
     */
    private function exportCredentials(array $rows, bool $dryRun): void
    {
        if ($dryRun) {
            $this->warn('Export ignoré en --dry-run (aucun compte créé).');

            return;
        }

        $path = (string) $this->option('export');
        $handle = fopen($path, 'w');

        if ($handle === false) {
            $this->error("Impossible d'écrire le fichier d'export : {$path}");

            return;
        }

        fputcsv($handle, ['company', 'employee_id', 'name', 'login_email', 'password']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['employee']->company->name ?? '',
                $row['employee']->id,
                $row['employee']->first_name.' '.$row['employee']->last_name,
                $row['email'],
                $row['password'],
            ]);
        }

        fclose($handle);

        $this->info("Identifiants exportés vers : {$path}");
    }
}
