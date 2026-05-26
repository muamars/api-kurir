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
            'category_id' => $this->category_id,
            'division_id' => $this->division_id,
            'tugas_pengiriman_id' => $this->tugas_pengiriman_id,
            'status' => $this->status,
            'priority' => $this->priority,
            'notes' => $this->notes,
            'deadline' => $this->deadline?->format('Y-m-d H:i:s'),
            'deadline_locked' => (bool) $this->deadline_locked,
            'scheduled_delivery_datetime' => $this->scheduled_delivery_datetime
            ? $this->scheduled_delivery_datetime->format('Y-m-d H:i:s')
            : null,
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

            'category' => $this->when($this->relationLoaded('category') && $this->category, [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'description' => $this->category?->description,
            ]),

            'vehicle_type' => $this->when($this->relationLoaded('vehicleType') && $this->vehicleType, [
                'id' => $this->vehicleType?->id,
                'name' => $this->vehicleType?->name,
                'code' => $this->vehicleType?->code,
                'description' => $this->vehicleType?->description,
            ]),

            'destinations' => $this->destinations->map(function ($destination) {
                return [
                    'id' => $destination->id,
                    'receiver_company' => $destination->receiver_company,
                    'receiver_name' => $destination->receiver_name,
                    'receiver_contact' => $destination->receiver_contact,
                    'delivery_address' => $destination->delivery_address,
                    'shipment_note' => $destination->shipment_note,
                    'sequence_order' => $destination->sequence_order,
                    'status' => $destination->status,
                ];
            }),

            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'no_referensi' => $item->no_referensi,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'description' => $item->description,
                ];
            }),

            'online_tracking_url' => $this->online_tracking_url,
            'shipping_cost' => $this->shipping_cost,
            'vehicle_used' => $this->vehicle_used,
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'cancel_reason' => $this->cancel_reason,
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'cancelled_by' => $this->when($this->cancelledBy, [
                'id' => $this->cancelledBy?->id,
                'name' => $this->cancelledBy?->name,
            ]),

            'progress_count' => $this->progress->count(),
            'completed_destinations' => $this->destinations->where('status', 'completed')->count(),
            'total_destinations' => $this->destinations->count(),
        ];
    }
}
