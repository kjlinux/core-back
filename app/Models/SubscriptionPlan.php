<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    public const FREEMIUM = 'freemium';

    public const GARANTIE = 'garantie';

    public const PREMIUM = 'premium';

    public const FEATURE_DASHBOARD = 'dashboard';

    public const FEATURE_EXPORT_RAW = 'export_raw';

    public const FEATURE_FIRMWARE_UPDATES = 'firmware_updates';

    public const FEATURE_PLATFORM_UPDATES = 'platform_updates';

    public const FEATURE_PAYROLL = 'payroll';

    public const FEATURE_HR_REPORTS = 'hr_reports';

    public const FEATURE_DEDICATED_SUPPORT = 'dedicated_support';

    public const FEATURE_SAV_INCLUDED = 'sav_included';

    public const FEATURE_ADVANCED_ANALYTICS = 'advanced_analytics';

    public const FEATURE_FIELD_VISITS = 'field_visits';

    protected $fillable = [
        'code',
        'name',
        'monthly_price_xof',
        'features',
        'requires_warranty',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'requires_warranty' => 'boolean',
        'is_active' => 'boolean',
        'monthly_price_xof' => 'integer',
        'sort_order' => 'integer',
    ];

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, (array) $this->features, true);
    }
}
