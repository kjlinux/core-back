<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Console\Command;

class RolloverPrepaidCommand extends Command
{
    protected $signature = 'subscriptions:rollover-prepaid';
    protected $description = 'Bascule les compagnies qui ont paye d\'avance vers leur nouvelle periode.';

    public function handle(SubscriptionService $service): int
    {
        $companies = Company::where('subscription_next_period_paid', true)
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<=', now())
            ->get();

        foreach ($companies as $company) {
            $service->rolloverPrepaid($company);
            $this->info("Compagnie {$company->name} : rollover effectue.");
        }

        $this->info(sprintf('Total : %d rollover(s).', $companies->count()));
        return self::SUCCESS;
    }
}
