<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'site_id',
        'department_id',
        'schedule_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'employee_number',
        'avatar',
        'is_active',
        'hire_date',
        'biometric_enrolled',
        'payment_mode',
        'base_salary',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'biometric_enrolled' => 'boolean',
        'hire_date' => 'date',
        'device_enrolled_at' => 'datetime',
        'base_salary' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function rfidCard(): HasOne
    {
        return $this->hasOne(RfidCard::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function fingerprintEnrollments(): HasMany
    {
        return $this->hasMany(FingerprintEnrollment::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }
}
