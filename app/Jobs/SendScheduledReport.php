<?php

namespace App\Jobs;

use App\Http\Controllers\Api\AdminSalesReportController;
use App\Http\Controllers\Api\AttendanceReportController;
use App\Http\Controllers\Api\FeelbackReportController;
use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use Carbon\Carbon;
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

        // Borne le rapport à la période écoulée dérivée de la fréquence. Sans cela
        // le rapport couvrirait tout l'historique et le rapport de présence — qui
        // exige start_date/end_date — échouerait silencieusement. Les filtres
        // explicites priment sur la fenêtre par défaut.
        $window = $schedule->reportingWindow();
        $query = array_merge($window, $schedule->filters ?? []);
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
            'feelback' => [app(FeelbackReportController::class), 'Rapport Feelback'],
            'sales' => [app(AdminSalesReportController::class), 'Rapport de ventes'],
            default => [null, ''],
        };
        if (! $controller) {
            // Type de rapport invalide : on NE retourne PAS silencieusement (sinon next_run_at
            // ne bouge jamais et le scheduler re-dispatche la meme planification en boucle).
            Log::error('SendScheduledReport: type de rapport invalide', [
                'schedule_id' => $schedule->id,
                'report_type' => $schedule->report_type,
            ]);
            $schedule->last_status = 'failed';
            $schedule->last_error = 'Type de rapport invalide : '.$schedule->report_type;
            $schedule->next_run_at = $schedule->computeNextRun();
            $schedule->save();

            return;
        }

        try {
            $response = $controller->{$method}($request);
            $content = $this->extractContent($response);

            $mime = $format === 'csv' ? 'text/csv' : 'application/pdf';
            $attachmentName = sprintf('%s_%s.%s', $schedule->report_type, now()->format('Y-m-d'), $format);
            $periodLabel = sprintf(
                'Du %s au %s',
                Carbon::parse($window['start_date'])->format('d/m/Y'),
                Carbon::parse($window['end_date'])->format('d/m/Y'),
            );

            $mail = new ScheduledReportMail($title, $periodLabel, $attachmentName, $content, $mime);

            // Envoi destinataire par destinataire : une erreur sur l'un ne doit pas annuler
            // les envois deja reussis ni marquer toute la planification en echec (livraison
            // partielle). On distingue success / partial / failed selon le nombre livre.
            $recipients = $schedule->recipients ?? [];
            $sent = 0;
            $failures = [];
            foreach ($recipients as $recipient) {
                try {
                    Mail::to($recipient)->send($mail);
                    $sent++;
                } catch (\Throwable $e) {
                    $failures[] = $recipient.' : '.$e->getMessage();
                    Log::warning('SendScheduledReport: echec d\'envoi a un destinataire', [
                        'schedule_id' => $schedule->id,
                        'recipient' => $recipient,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($sent > 0) {
                $schedule->last_sent_at = now();
            }

            if (empty($failures)) {
                $schedule->last_status = 'success';
                $schedule->last_error = null;
            } elseif ($sent > 0) {
                $schedule->last_status = 'partial';
                $schedule->last_error = mb_substr(
                    sprintf('%d/%d destinataire(s) en echec : %s', count($failures), count($recipients), implode(' | ', $failures)),
                    0,
                    500,
                );
            } else {
                $schedule->last_status = 'failed';
                $schedule->last_error = mb_substr('Aucun destinataire livre : '.implode(' | ', $failures), 0, 500);
            }

            $schedule->next_run_at = $schedule->computeNextRun();
            $schedule->save();
        } catch (\Throwable $e) {
            // Echec en amont de l'envoi (generation du rapport) : echec complet.
            Log::error('SendScheduledReport failed', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);
            // On reporte la prochaine tentative pour éviter une boucle d'échec immédiate
            // et on conserve l'erreur pour la rendre visible dans le tableau des planifications.
            $schedule->last_status = 'failed';
            $schedule->last_error = mb_substr($e->getMessage(), 0, 500);
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
