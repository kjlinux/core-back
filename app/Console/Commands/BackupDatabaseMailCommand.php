<?php

namespace App\Console\Commands;

use App\Mail\DatabaseBackupMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\Process;

class BackupDatabaseMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup-mail
        {--keep=7 : Nombre de sauvegardes locales a conserver dans storage/app/backups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genere un dump PostgreSQL compresse (gzip) et l\'envoie en piece jointe aux destinataires de sauvegarde';

    /**
     * Destinataires de la sauvegarde quotidienne.
     *
     * @var array<int, string>
     */
    private const RECIPIENTS = [
        'contact@tangagroup.com',
        'koffijude01@gmail.com',
    ];

    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $fileName = ($db['database'] ?? 'database').'-'.now()->format('Y-m-d_His').'.sql.gz';
        $filePath = $dir.DIRECTORY_SEPARATOR.$fileName;

        $command = sprintf(
            'pg_dump --no-owner --no-privileges --clean --if-exists -h %s -p %s -U %s -d %s | gzip -9 > %s',
            escapeshellarg((string) ($db['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($db['port'] ?? 5432)),
            escapeshellarg((string) ($db['username'] ?? '')),
            escapeshellarg((string) ($db['database'] ?? '')),
            escapeshellarg($filePath),
        );

        $process = Process::fromShellCommandline($command, null, [
            'PGPASSWORD' => (string) ($db['password'] ?? ''),
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($filePath) || filesize($filePath) === 0) {
            @unlink($filePath);
            $message = 'Echec du dump PostgreSQL: '.trim($process->getErrorOutput());
            $this->error($message);
            Log::error('[db:backup-mail] '.$message);

            return self::FAILURE;
        }

        $humanSize = $this->humanSize((int) filesize($filePath));
        $this->info("Dump genere: {$fileName} ({$humanSize})");

        try {
            Mail::to(self::RECIPIENTS)->send(new DatabaseBackupMail(
                $filePath,
                $fileName,
                now()->format('d/m/Y H:i'),
                $humanSize,
            ));
            $this->info('Sauvegarde envoyee a: '.implode(', ', self::RECIPIENTS));
        } catch (\Throwable $e) {
            $this->error('Echec envoi email: '.$e->getMessage());
            Log::error('[db:backup-mail] Echec envoi email: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->pruneOldBackups($dir, (int) $this->option('keep'));

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $dir, int $keep): void
    {
        $files = glob($dir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, max($keep, 1)) as $old) {
            @unlink($old);
        }
    }

    private function humanSize(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 2).' '.$units[$i];
    }
}
