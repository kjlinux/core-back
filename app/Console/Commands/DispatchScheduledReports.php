<?php

namespace App\Console\Commands;

use App\Jobs\SendScheduledReport;
use App\Models\ReportSchedule;
use Illuminate\Console\Command;

class DispatchScheduledReports extends Command
{
    protected $signature = 'reports:dispatch-scheduled';

    protected $description = 'Dispatch les rapports planifiés dont l\'échéance est atteinte';

    public function handle(): int
    {
        $due = ReportSchedule::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->get();

        foreach ($due as $schedule) {
            SendScheduledReport::dispatch($schedule->id);
            $this->info("Dispatched schedule {$schedule->id} ({$schedule->report_type})");
        }

        $this->info("Total: {$due->count()} planification(s) dispatchee(s).");

        return self::SUCCESS;
    }
}
