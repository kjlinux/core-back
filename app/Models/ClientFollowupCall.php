<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientFollowupCall extends Model
{
    public const TYPE_J2 = 'j2';

    public const TYPE_J7 = 'j7';

    public const TYPE_J30 = 'j30';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_ESCALATED = 'escalated';

    public const RESULT_OK = 'ok';

    public const RESULT_PARTIAL = 'partial';

    public const RESULT_PROBLEM = 'problem';

    protected $fillable = [
        'company_id', 'installation_sheet_id', 'call_type',
        'scheduled_at', 'called_at', 'status', 'result',
        'usage_rate', 'satisfaction_score',
        'notes', 'actions', 'assigned_to_user_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'called_at' => 'datetime',
        'usage_rate' => 'float',
        'satisfaction_score' => 'integer',
        'actions' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function installationSheet(): BelongsTo
    {
        return $this->belongsTo(InstallationSheet::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
