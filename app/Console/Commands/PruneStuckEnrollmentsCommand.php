<?php

namespace App\Console\Commands;

use App\Models\FingerprintEnrollment;
use Illuminate\Console\Command;

class PruneStuckEnrollmentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'biometric:prune-stuck-enrollments
        {--pending-minutes=15 : Age (minutes) au-dela duquel un enrolement encore "pending" est purge}
        {--failed-hours=24 : Age (heures) au-dela duquel un enrolement "failed" est purge}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge les enrolements biometriques bloques (pending jamais confirme, failed ancien) pour liberer les slots et eviter les faux "enrole mais refuse"';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $pendingMinutes = (int) $this->option('pending-minutes');
        $failedHours = (int) $this->option('failed-hours');

        $stalePending = FingerprintEnrollment::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes($pendingMinutes))
            ->delete();

        $staleFailed = FingerprintEnrollment::query()
            ->where('status', 'failed')
            ->where('created_at', '<', now()->subHours($failedHours))
            ->delete();

        if ($stalePending > 0 || $staleFailed > 0) {
            $this->info("Enrolements purges -- pending: {$stalePending}, failed: {$staleFailed}");
        }

        return self::SUCCESS;
    }
}
