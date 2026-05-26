<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallationSheet extends Model
{
    use HasUuid;

    public const SOLUTIONS = [
        'presenseRH_rfid', 'presenseRH_fp', 'presenseRH_qr',
        'feelback', 'smartcard', 'kuilinga',
    ];

    protected $fillable = [
        'company_id', 'technician_user_id',
        'client_contact_name', 'client_contact_role', 'client_phone', 'client_email', 'site_address',
        'solution', 'serial_number', 'quantity', 'firmware_version',
        'wifi_ssid', 'static_ip', 'remote_access',
        'checklist', 'training_rating', 'observations',
        'client_signature_path', 'technician_signature_path',
        'installed_at',
    ];

    protected $casts = [
        'checklist' => 'array',
        'installed_at' => 'datetime',
        'training_rating' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(ClientFollowupCall::class, 'installation_sheet_id');
    }
}
