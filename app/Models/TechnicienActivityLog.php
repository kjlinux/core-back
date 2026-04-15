<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicienActivityLog extends Model
{
    protected $fillable = [
        'technicien_id',
        'company_id',
        'action',
        'resource_type',
        'resource_id',
        'resource_label',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function technicien(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technicien_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Enregistre une activite technicien si l'utilisateur est technicien ou super_admin avec company active.
     */
    public static function record(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?string $resourceLabel = null,
        ?array $metadata = null,
    ): void {
        $user = auth()->user();
        if (!$user) return;

        // On ne loggue que les actions des techniciens
        if (!$user->isTechnicien()) return;

        $companyId = request()->input('_company_id') ?? $user->company_id;
        if (!$companyId) return;

        static::create([
            'technicien_id'  => $user->id,
            'company_id'     => $companyId,
            'action'         => $action,
            'resource_type'  => $resourceType,
            'resource_id'    => $resourceId,
            'resource_label' => $resourceLabel,
            'metadata'       => $metadata,
        ]);
    }
}
