<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'channel' => $this->channel,
            'recommendations' => $this->recommendations,
            'answers' => $this->when(
                $this->relationLoaded('answers'),
                fn () => $this->answers->map(fn ($a) => [
                    'questionId' => (string) $a->review_question_id,
                    'stars' => $a->stars,
                ])
            ),
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
