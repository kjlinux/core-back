<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicienReport extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'company_id',
        'company_name',
        'technicien_name',
        'global_score',
        'payload',
        'payload_hash',
        'signature',
        'signed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'global_score' => 'integer',
        'signed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Hash canonique du payload (clés triées) pour rendre la signature reproductible.
     */
    public static function canonicalHash(array $payload): string
    {
        return hash('sha256', self::canonicalize($payload));
    }

    public static function sign(string $payloadHash): string
    {
        return hash_hmac('sha256', $payloadHash, config('app.key'));
    }

    private static function canonicalize(mixed $value): string
    {
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            if ($isAssoc) {
                ksort($value);
                $parts = [];
                foreach ($value as $k => $v) {
                    $parts[] = json_encode($k).':'.self::canonicalize($v);
                }

                return '{'.implode(',', $parts).'}';
            }

            return '['.implode(',', array_map([self::class, 'canonicalize'], $value)).']';
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
