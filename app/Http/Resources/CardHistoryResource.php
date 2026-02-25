<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'cardId' => (string) $this->card_id,
            'action' => $this->action,
            'performedBy' => $this->performed_by,
            'timestamp' => $this->created_at?->toISOString(),
            'details' => $this->details,
        ];
    }
}
