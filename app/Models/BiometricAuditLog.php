<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BiometricAuditLog extends Model
{
    use HasUuid;

    protected $table = 'biometric_audit_log';

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'target',
        'details',
    ];
}
