<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasUuid, BelongsToCompany;

    protected $fillable = [
        'order_number',
        'company_id',
        'subtotal',
        'delivery_fee',
        'total',
        'currency',
        'status',
        'payment_method',
        'payment_status',
        'delivery_address',
        'invoice_url',
        'payment_token',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'delivery_fee' => 'integer',
        'total' => 'integer',
        'delivery_address' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
