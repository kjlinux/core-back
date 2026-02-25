<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardHistory extends Model
{
    use HasUuid;

    protected $table = 'card_history';

    protected $fillable = [
        'card_id',
        'action',
        'performed_by',
        'details',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(RfidCard::class, 'card_id');
    }
}
