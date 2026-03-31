<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FirmwareVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'version' => $this->version,
            'deviceKind' => $this->device_kind,
            'description' => $this->description,
            'fileUrl' => $this->file_path ? Storage::disk('public')->url($this->file_path) : null,
            'fileSize' => $this->file_size,
            'isAutoUpdate' => $this->is_auto_update,
            'isPublished'  => $this->is_published,
            'publishedAt'  => $this->published_at?->toISOString(),
            'uploadedAt'   => $this->created_at?->toISOString(),
            'uploadedBy' => $this->when(
                $this->relationLoaded('uploader'),
                fn() => $this->uploader
                    ? $this->uploader->first_name . ' ' . $this->uploader->last_name
                    : null
            ),
        ];
    }
}
