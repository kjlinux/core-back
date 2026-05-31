<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'geofence_radius',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'geofence_radius' => 'integer',
    ];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function biometricDevices(): HasMany
    {
        return $this->hasMany(BiometricDevice::class);
    }

    public function feelbackDevices(): HasMany
    {
        return $this->hasMany(FeelbackDevice::class);
    }
}
