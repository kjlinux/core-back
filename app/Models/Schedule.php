<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory, HasUuid, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'work_days',
        'late_tolerance',
        'assigned_departments',
    ];

    protected $casts = [
        'work_days' => 'array',
        'assigned_departments' => 'array',
        'late_tolerance' => 'integer',
    ];
}
