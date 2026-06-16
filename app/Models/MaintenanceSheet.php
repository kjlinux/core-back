<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceSheet extends Model
{
    use HasUuid;

    public const TYPES = ['preventive', 'corrective', 'emergency'];

    public const EQUIPMENT_STATUSES = ['operational', 'repaired', 'replaced', 'to_monitor', 'out_of_service'];

    /**
     * Catalogue des solutions ; identique a la fiche d'installation puisque la
     * maintenance porte sur le meme materiel.
     */
    public const SOLUTIONS = InstallationSheet::SOLUTIONS;

    /**
     * @var list<string>
     */
    protected $appends = ['client_signature_url', 'technician_signature_url'];

    protected $fillable = [
        'company_id', 'installation_sheet_id', 'technician_user_id',
        'client_contact_name', 'client_contact_role', 'client_phone', 'client_email', 'site_address',
        'maintenance_type', 'reported_issue',
        'equipments', 'checklist',
        'resolved', 'duration_minutes', 'satisfaction_rating', 'next_maintenance_at',
        'observations',
        'client_signature_path', 'technician_signature_path',
        'maintained_at',
    ];

    protected $casts = [
        'equipments' => 'array',
        'checklist' => 'array',
        'resolved' => 'boolean',
        'duration_minutes' => 'integer',
        'satisfaction_rating' => 'integer',
        'next_maintenance_at' => 'date',
        'maintained_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function installationSheet(): BelongsTo
    {
        return $this->belongsTo(InstallationSheet::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
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
