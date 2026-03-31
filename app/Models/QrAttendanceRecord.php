<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrAttendanceRecord extends Model
{
    use HasFactory, HasUuid, BelongsToCompany;

    protected $fillable = [
        'employee_id',
        'qr_code_id',
        'company_id',
        'date',
        'entry_time',
        'exit_time',
        'status',
        'scanned_at',
        'scanned_by_device_id',
        'notes',
        'device_fingerprint',
        'scan_latitude',
        'scan_longitude',
        'gps_verified',
        'distance_meters',
    ];

    protected $casts = [
        'date' => 'date',
        'scanned_at' => 'datetime',
        'gps_verified' => 'boolean',
        'scan_latitude' => 'float',
        'scan_longitude' => 'float',
        'distance_meters' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class, 'qr_code_id');
    }
}
