<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceRequest extends Model
{
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'employee_id',
        'company_id',
        'date_start',
        'date_end',
        'reason',
        'justificatif_url',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'date_start' => 'date',
        'date_end' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Indique si la demande couvre la date donnee et a ete approuvee.
     */
    public function coversDate(string $date): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }

        return $date >= $this->date_start->toDateString()
            && $date <= $this->date_end->toDateString();
    }
}
