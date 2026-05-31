<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiringReminderMail;
use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReminderSent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie un rappel a J-7, J-3 et J-1 avant la date d'echeance.
 * Idempotence garantie en base : un rappel (compagnie, jours_restants, jour d'envoi) n'est
 * emis qu'une seule fois grace a la table subscription_reminders_sent (contrainte unique).
 * Une double execution accidentelle le meme jour ne renverra donc pas de mail.
 */
class SendSubscriptionRemindersCommand extends Command
{
    protected $signature = 'subscriptions:send-reminders';

    protected $description = 'Envoie les rappels J-7 / J-3 / J-1 avant expiration d\'abonnement.';

    public function handle(): int
    {
        $sent = 0;
        foreach ([7, 3, 1] as $days) {
            $target = now()->addDays($days)->startOfDay();
            $companies = Company::where('subscription', '!=', SubscriptionPlan::FREEMIUM)
                ->whereNotNull('subscription_expires_at')
                ->whereBetween('subscription_expires_at', [$target, $target->copy()->endOfDay()])
                ->where('subscription_next_period_paid', false)
                ->get();

            foreach ($companies as $company) {
                if (! $company->email) {
                    continue;
                }

                $reminder = SubscriptionReminderSent::firstOrCreate([
                    'company_id' => $company->id,
                    'days_left' => $days,
                    'sent_on' => now()->toDateString(),
                ]);

                if (! $reminder->wasRecentlyCreated) {
                    continue;
                }

                Mail::to($company->email)->queue(new SubscriptionExpiringReminderMail($company, $days));
                $sent++;
            }
        }

        $this->info(sprintf('Rappels envoyes : %d', $sent));

        return self::SUCCESS;
    }
}
