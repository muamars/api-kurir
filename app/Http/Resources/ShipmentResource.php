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
            'bulk_assignment_id' => $this->relationLoaded('bulkAssignment') || isset($this->bulk_assignment_id) ? $this->bulk_assignment_id : null,
            'category_id' => $this->relationLoaded('category') ? $this->category?->name : null,
            'division_id' => $this->relationLoaded('division') ? $this->division?->name : null,
            'division_name' => $this->relationLoaded('creator')
                ? ($this->creator?->relationLoaded('division') ? $this->creator?->division?->name : ($this->relationLoaded('division') ? $this->division?->name : null))
                : ($this->relationLoaded('division') ? $this->division?->name : null),
            'tugas_pengiriman_id' => $this->relationLoaded('tugasPengiriman') ? $this->tugasPengiriman?->tugas : null,
            'status' => $this->status,
            'priority' => $this->priority,
            'takeover_count' => (int) $this->takeover_count,
            'needs_review' => (bool) $this->needs_review,
            'last_takeover_at' => $this->last_takeover_at?->format('Y-m-d H:i:s'),
            'notes' => $this->notes,
            'deadline' => $this->deadline?->format('Y-m-d H:i:s'),
            'deadline_locked' => (bool) $this->deadline_locked,
            'scheduled_delivery_datetime' => $this->scheduled_delivery_datetime
                ? $this->scheduled_delivery_datetime->format('Y-m-d H:i:s')
                : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),

            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'division' => $this->creator?->relationLoaded('division') ? $this->creator?->division?->name : ($this->relationLoaded('division') ? $this->division?->name : null),
            ]),

            'approver' => $this->whenLoaded('approver', fn () => [
                'id' => $this->approver?->id,
                'name' => $this->approver?->name,
            ]),

            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver?->id,
                'name' => $this->driver?->name,
                'phone' => $this->driver?->phone,
            ]),

            'category' => $this->when($this->relationLoaded('category') && $this->category, fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'description' => $this->category?->description,
            ]),

            'vehicle_type' => $this->when($this->relationLoaded('vehicleType') && $this->vehicleType, fn () => [
                'id' => $this->vehicleType?->id,
                'name' => $this->vehicleType?->name,
                'code' => $this->vehicleType?->code,
                'description' => $this->vehicleType?->description,
            ]),

            'destinations' => $this->whenLoaded('destinations', fn () => $this->destinations->map(function ($destination) {
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
            })),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'no_referensi' => $item->no_referensi,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'description' => $item->description,
                ];
            })),

            'online_tracking_url' => $this->online_tracking_url,
            'shipping_cost' => $this->shipping_cost,
            'vehicle_used' => $this->vehicle_used,
            'surat_pengantar_kerja' => $this->surat_pengantar_kerja ? asset('storage/'.$this->surat_pengantar_kerja) : null,
            'proof_photo_url' => $this->proof_photo_url,
            'photos' => ShipmentPhotoResource::collection($this->whenLoaded('photos')),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'cancel_reason' => $this->cancel_reason,
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'cancelled_by' => $this->whenLoaded('cancelledBy', fn () => [
                'id' => $this->cancelledBy?->id,
                'name' => $this->cancelledBy?->name,
            ]),

            'progress_count' => $this->progress_count ?? ($this->relationLoaded('progress') ? $this->progress->count() : 0),
            'completed_destinations' => $this->relationLoaded('destinations') ? $this->destinations->where('status', 'completed')->count() : 0,
            'total_destinations' => $this->relationLoaded('destinations') ? $this->destinations->count() : 0,
        ];
    }
}
