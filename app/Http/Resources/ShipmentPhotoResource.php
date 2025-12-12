<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentPhotoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'type' => $this->type,
            'photo_url' => asset('storage/'.$this->photo_url),
            'photo_thumbnail' => $this->photo_thumbnail ? asset('storage/'.$this->photo_thumbnail) : null,
            'notes' => $this->notes,
            'uploaded_by' => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ],
            'uploaded_at' => $this->uploaded_at->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
