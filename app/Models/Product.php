<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'description',
        'category',
        'price',
        'currency',
        'stock_quantity',
        'images',
        'customizable',
        'min_quantity',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'stock_quantity' => 'integer',
        'images' => 'array',
        'customizable' => 'boolean',
        'min_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
