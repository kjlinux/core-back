<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => SubscriptionPlan::FREEMIUM,
                'name' => 'Freemium',
                'monthly_price_xof' => 0,
                'requires_warranty' => false,
                'sort_order' => 1,
                'features' => [
                    SubscriptionPlan::FEATURE_DASHBOARD,
                    SubscriptionPlan::FEATURE_EXPORT_RAW,
                ],
            ],
            [
                'code' => SubscriptionPlan::GARANTIE,
                'name' => 'Abonnement Garantie',
                'monthly_price_xof' => 15000,
                'requires_warranty' => true,
                'sort_order' => 2,
                'features' => [
                    SubscriptionPlan::FEATURE_DASHBOARD,
                    SubscriptionPlan::FEATURE_EXPORT_RAW,
                    SubscriptionPlan::FEATURE_FIRMWARE_UPDATES,
                    SubscriptionPlan::FEATURE_PLATFORM_UPDATES,
                    SubscriptionPlan::FEATURE_PAYROLL,
                    SubscriptionPlan::FEATURE_HR_REPORTS,
                    SubscriptionPlan::FEATURE_DEDICATED_SUPPORT,
                    SubscriptionPlan::FEATURE_SAV_INCLUDED,
                ],
            ],
            [
                'code' => SubscriptionPlan::PREMIUM,
                'name' => 'Abonnement Premium',
                'monthly_price_xof' => 30000,
                'requires_warranty' => false,
                'sort_order' => 3,
                'features' => [
                    SubscriptionPlan::FEATURE_DASHBOARD,
                    SubscriptionPlan::FEATURE_EXPORT_RAW,
                    SubscriptionPlan::FEATURE_FIRMWARE_UPDATES,
                    SubscriptionPlan::FEATURE_PLATFORM_UPDATES,
                    SubscriptionPlan::FEATURE_PAYROLL,
                    SubscriptionPlan::FEATURE_HR_REPORTS,
                    SubscriptionPlan::FEATURE_DEDICATED_SUPPORT,
                    SubscriptionPlan::FEATURE_SAV_INCLUDED,
                    SubscriptionPlan::FEATURE_ADVANCED_ANALYTICS,
                    SubscriptionPlan::FEATURE_FIELD_VISITS,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
