<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiringReminderMail;
use App\Models\Company;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie un rappel a J-7, J-3 et J-1 avant la date d'echeance.
 * Idempotence simple : on se base sur l'heure d'execution (matin), on n'envoie
 * qu'une seule fois par jour pour une compagnie donnee.
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
                if ($company->email) {
                    Mail::to($company->email)->queue(new SubscriptionExpiringReminderMail($company, $days));
                    $sent++;
                }
            }
        }

        $this->info(sprintf('Rappels envoyes : %d', $sent));
        return self::SUCCESS;
    }
}
