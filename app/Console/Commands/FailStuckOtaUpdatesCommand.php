<?php

namespace App\Console\Commands;

use App\Models\OtaUpdateLog;
use Illuminate\Console\Command;

class FailStuckOtaUpdatesCommand extends Command
{
    protected $signature = 'firmware:fail-stuck-ota {--minutes=10}';

    protected $description = 'Marque comme failed les mises a jour OTA bloquees en pending/in_progress au-dela du delai.';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $cutoff = now()->subMinutes($minutes);

        $stuck = OtaUpdateLog::whereIn('status', ['pending', 'in_progress'])
            ->where('started_at', '<=', $cutoff)
            ->get();

        foreach ($stuck as $log) {
            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => "Timeout : aucune reponse du terminal apres {$minutes} minutes.",
            ]);
        }

        $this->info("OTA bloquees marquees failed : {$stuck->count()}");

        return self::SUCCESS;
    }
}
