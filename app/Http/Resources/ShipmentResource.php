<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
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
            'status' => $this->status,
            'priority' => $this->priority,
            'notes' => $this->notes,
            'deadline' => $this->deadline?->format('Y-m-d'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),

            'creator' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'division' => $this->creator->division->name ?? null,
            ],

            'approver' => $this->when($this->approver, [
                'id' => $this->approver?->id,
                'name' => $this->approver?->name,
            ]),

            'driver' => $this->when($this->driver, [
                'id' => $this->driver?->id,
                'name' => $this->driver?->name,
                'phone' => $this->driver?->phone,
            ]),

            'destinations' => $this->destinations->map(function ($destination) {
                return [
                    'id' => $destination->id,
                    'receiver_name' => $destination->receiver_name,
                    'delivery_address' => $destination->delivery_address,
                    'shipment_note' => $destination->shipment_note,
                    'sequence_order' => $destination->sequence_order,
                    'status' => $destination->status,
                ];
            }),

            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'description' => $item->description,
                ];
            }),

            'progress_count' => $this->progress->count(),
            'completed_destinations' => $this->destinations->where('status', 'completed')->count(),
            'total_destinations' => $this->destinations->count(),
        ];
    }
}
