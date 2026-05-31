<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DeviceLogsDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\DeviceLog>  $logs
     */
    public function __construct(
        public Collection $logs,
        public CarbonInterface $periodStart,
        public CarbonInterface $periodEnd,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->logs->count();

        $subject = $count > 0
            ? "Tangaflow : {$count} log(s) terminaux sur 24h"
            : 'Tangaflow : terminaux, aucun incident sur 24h';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        /** @var array<string, int> $levelCounts */
        $levelCounts = $this->logs
            ->groupBy('level')
            ->map(static fn (Collection $group): int => $group->count())
            ->all();

        $byDevice = $this->logs
            ->sortByDesc('created_at')
            ->groupBy(static fn ($log): string => $log->serial_number ?: 'Terminal inconnu');

        return new Content(
            view: 'emails.device-logs-digest',
            with: [
                'totalCount' => $this->logs->count(),
                'levelCounts' => $levelCounts,
                'byDevice' => $byDevice,
                'periodStart' => $this->periodStart,
                'periodEnd' => $this->periodEnd,
            ],
        );
    }
}
