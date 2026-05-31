<?php

namespace App\Console\Commands;

use App\Mail\DeviceLogsDigestMail;
use App\Models\DeviceLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDeviceLogsDigest extends Command
{
    protected $signature = 'device-logs:send-digest';

    protected $description = 'Envoie par mail le recapitulatif des logs terminaux des dernieres 24h (warning/error/critical)';

    public function handle(): int
    {
        $periodEnd = now();
        $periodStart = $periodEnd->copy()->subDay();

        $logs = DeviceLog::query()
            ->where('created_at', '>=', $periodStart)
            ->orderByDesc('created_at')
            ->get();

        $recipients = $this->resolveRecipients();

        if (empty($recipients)) {
            $this->warn('Aucun destinataire configure pour le digest des logs terminaux.');

            return self::SUCCESS;
        }

        Mail::to($recipients)->queue(new DeviceLogsDigestMail($logs, $periodStart, $periodEnd));

        $this->info(sprintf(
            'Digest des logs terminaux mis en file pour %d destinataire(s) (%d log(s)).',
            count($recipients),
            $logs->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Destinataires : tous les utilisateurs support_it actifs + les adresses fixes (.env).
     *
     * @return array<int, string>
     */
    private function resolveRecipients(): array
    {
        $support = User::query()
            ->where('role', 'support_it')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->all();

        $extra = config('device_logs.digest_extra_recipients', []);

        return array_values(array_unique(array_merge($support, $extra)));
    }
}
