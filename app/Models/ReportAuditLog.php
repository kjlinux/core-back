<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAuditLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'company_id',
        'report_type',
        'route',
        'filters',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
