<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'role',
        'company_id',
        'employee_id',
        'avatar',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TechnicienActivityLog::class, 'technicien_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdminEnterprise(): bool
    {
        return $this->role === 'admin_enterprise';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isTechnicien(): bool
    {
        return $this->role === 'technicien';
    }

    public function isSetupRole(): bool
    {
        return in_array($this->role, ['super_admin', 'technicien']);
    }

    public function isEmploye(): bool
    {
        return $this->role === 'employe';
    }

    public function isAdminOrAbove(): bool
    {
        return in_array($this->role, ['super_admin', 'admin_enterprise', 'technicien']);
    }
}
