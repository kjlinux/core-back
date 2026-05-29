<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasUuid;

    protected $fillable = [
        'employee_id',
        'date',
        'entry_time',
        'exit_time',
        'status',
        'late_minutes',
        'early_departure_minutes',
        'overtime_minutes',
        'source',
        'is_double_badge',
        'notes',
        'expected_shift',
        'segments',
        'is_on_leave',
    ];

    protected $casts = [
        'date' => 'date',
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
        'late_minutes' => 'integer',
        'early_departure_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'is_double_badge' => 'boolean',
        'is_on_leave' => 'boolean',
        'segments' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
