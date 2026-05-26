<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiredMail;
use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckSubscriptionExpiryCommand extends Command
{
    protected $signature = 'subscriptions:check-expiry';
    protected $description = 'Expire les abonnements arrives a echeance (bascule en Freemium) et envoie un mail.';

    public function handle(SubscriptionService $service): int
    {
        $expired = Company::where('subscription', '!=', SubscriptionPlan::FREEMIUM)
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<', now())
            ->where('subscription_next_period_paid', false)
            ->get();

        foreach ($expired as $company) {
            $previousPlan = $company->subscription;
            $service->expire($company);
            if ($company->email) {
                Mail::to($company->email)->queue(new SubscriptionExpiredMail($company, $previousPlan));
            }
            $this->info("Compagnie {$company->name} expiree depuis {$previousPlan}.");
        }

        $this->info(sprintf('Total : %d compagnie(s) expiree(s).', $expired->count()));
        return self::SUCCESS;
    }
}
