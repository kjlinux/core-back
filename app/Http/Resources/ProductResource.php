<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'price' => $this->price,
            'currency' => $this->currency,
            'stockQuantity' => $this->stock_quantity,
            'images' => $this->images,
            'customizable' => (bool) $this->customizable,
            'minQuantity' => $this->min_quantity,
            'isActive' => (bool) $this->is_active,
        ];
    }
}
