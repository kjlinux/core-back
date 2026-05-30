<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallationSheet extends Model
{
    use HasUuid;

    public const SOLUTIONS = [
        'presenseRH_rfid', 'presenseRH_fp', 'presenseRH_qr',
        'feelback',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['client_signature_url', 'technician_signature_url'];

    protected $fillable = [
        'company_id', 'technician_user_id',
        'client_contact_name', 'client_contact_role', 'client_phone', 'client_email', 'site_address',
        'materials',
        'checklist', 'training_rating', 'observations',
        'client_signature_path', 'technician_signature_path',
        'installed_at',
    ];

    protected $casts = [
        'materials' => 'array',
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

    protected function clientSignatureUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->client_signature_path
                ? asset('storage/'.$this->client_signature_path)
                : null,
        );
    }

    protected function technicianSignatureUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->technician_signature_path
                ? asset('storage/'.$this->technician_signature_path)
                : null,
        );
    }
}
