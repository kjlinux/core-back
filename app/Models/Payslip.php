<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasUuid;

    protected $fillable = [
        'employee_id',
        'company_id',
        'site_id',
        'department_id',
        'period',
        'period_start',
        'period_end',
        'payment_mode',
        'base_salary',
        'worked_days',
        'worked_hours',
        'absent_days',
        'total_lateness_minutes',
        'overtime_hours',
        'overtime_amount',
        'lateness_deduction',
        'absence_deduction',
        'lines',
        'gross_amount',
        'net_amount',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'period_start'           => 'date',
        'period_end'             => 'date',
        'worked_days'            => 'integer',
        'worked_hours'           => 'float',
        'absent_days'            => 'integer',
        'total_lateness_minutes' => 'integer',
        'overtime_hours'         => 'float',
        'overtime_amount'        => 'integer',
        'lateness_deduction'     => 'integer',
        'absence_deduction'      => 'integer',
        'lines'                  => 'array',
        'gross_amount'           => 'integer',
        'net_amount'             => 'integer',
        'base_salary'            => 'integer',
        'generated_at'           => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
