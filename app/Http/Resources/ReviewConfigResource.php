<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'token' => $this->token,
            'isActive' => (bool) $this->is_active,
            'questions' => $this->when(
                $this->relationLoaded('questions'),
                fn () => $this->questions->map(fn ($q) => [
                    'id' => (string) $q->id,
                    'text' => $q->text,
                    'orderIndex' => $q->order_index,
                ])
            ),
            'channels' => $this->when(
                $this->relationLoaded('channels'),
                fn () => $this->channels->map(fn ($c) => [
                    'id' => (string) $c->id,
                    'name' => $c->name,
                ])
            ),
            'reviewUrl' => rtrim(explode(',', env('FRONTEND_URL', config('app.url')))[0], '/') . '/avis/' . $this->token,
        ];
    }
}
