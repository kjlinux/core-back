<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'logo',
        'email',
        'phone',
        'address',
        'matricule_prefix',
        'is_active',
        'subscription',
        'subscription_starts_at',
        'subscription_expires_at',
        'subscription_next_period_paid',
        'subscription_next_expires_at',
        'warranty_starts_at',
        'warranty_ends_at',
        'warranty_auto_renew',
        'subscription_pending_change_to',
        'is_test',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_test' => 'boolean',
        'subscription_next_period_paid' => 'boolean',
        'subscription_starts_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
        'subscription_next_expires_at' => 'datetime',
        'warranty_starts_at' => 'datetime',
        'warranty_ends_at' => 'datetime',
        'warranty_auto_renew' => 'boolean',
    ];

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function subscriptionHistory(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class);
    }

    public function isSubscriptionActive(): bool
    {
        return $this->subscription !== SubscriptionPlan::FREEMIUM
            && $this->subscription_expires_at
            && $this->subscription_expires_at->isFuture();
    }

    public function isWarrantyActive(): bool
    {
        // Une garantie a renouvellement automatique reste active tant qu'elle n'est pas arretee.
        if ($this->warranty_auto_renew) {
            return true;
        }

        return $this->warranty_ends_at && $this->warranty_ends_at->isFuture();
    }

    /**
     * Administrateur principal de l'entreprise (premier compte admin_enterprise).
     */
    public function admin(): HasOne
    {
        return $this->hasOne(User::class)
            ->where('role', 'admin_enterprise')
            ->oldest('created_at');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function rfidCards(): HasMany
    {
        return $this->hasMany(RfidCard::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
