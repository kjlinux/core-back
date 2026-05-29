<?php

namespace App\Jobs;

use App\Http\Controllers\Api\AdminSalesReportController;
use App\Http\Controllers\Api\AttendanceReportController;
use App\Http\Controllers\Api\FeelbackReportController;
use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SendScheduledReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $scheduleId) {}

    public function handle(): void
    {
        $schedule = ReportSchedule::with('user')->find($this->scheduleId);
        if (! $schedule || ! $schedule->is_active || ! $schedule->user) {
            return;
        }

        // Le rapport est généré dans le contexte de l'utilisateur propriétaire (scoping company).
        auth()->setUser($schedule->user);

        $query = $schedule->filters ?? [];
        if ($schedule->company_id) {
            $query['company_id'] = $schedule->company_id;
            $query['_company_id'] = $schedule->company_id;
        }
        $request = Request::create('/', 'GET', $query);
        $request->setUserResolver(fn () => $schedule->user);

        $format = $schedule->format ?: 'pdf';
        $method = $format === 'csv' ? 'exportCsv' : 'exportPdf';

        [$controller, $title] = match ($schedule->report_type) {
            'attendance' => [app(AttendanceReportController::class), 'Rapport de presence'],
            'feelback'   => [app(FeelbackReportController::class), 'Rapport Feelback'],
            'sales'      => [app(AdminSalesReportController::class), 'Rapport de ventes'],
            default      => [null, ''],
        };
        if (! $controller) {
            return;
        }

        try {
            $response = $controller->{$method}($request);
            $content = $this->extractContent($response);

            $mime = $format === 'csv' ? 'text/csv' : 'application/pdf';
            $attachmentName = sprintf('%s_%s.%s', $schedule->report_type, now()->format('Y-m-d'), $format);
            $periodLabel = now()->format('d/m/Y');

            $mail = new ScheduledReportMail($title, $periodLabel, $attachmentName, $content, $mime);

            foreach ($schedule->recipients as $recipient) {
                Mail::to($recipient)->send($mail);
            }

            $schedule->last_sent_at = now();
            $schedule->next_run_at = $schedule->computeNextRun();
            $schedule->save();
        } catch (\Throwable $e) {
            Log::error('SendScheduledReport failed', [
                'schedule_id' => $schedule->id,
                'error'       => $e->getMessage(),
            ]);
            // On reporte la prochaine tentative pour éviter une boucle d'échec immédiate.
            $schedule->next_run_at = $schedule->computeNextRun();
            $schedule->save();
        }
    }

    private function extractContent(mixed $response): string
    {
        if ($response instanceof StreamedResponse) {
            ob_start();
            $response->sendContent();
            return (string) ob_get_clean();
        }

        return (string) $response->getContent();
    }
}
