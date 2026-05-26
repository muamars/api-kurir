<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShipmentArchiveService
{
    /**
     * Arsipkan satu shipment secara langsung (dipanggil setelah complete/cancel).
     * Menyimpan snapshot ke shipment_history lalu menandai is_archived = true.
     */
    public function archive(Shipment $shipment, string $reason = 'completed'): ShipmentHistory
    {
        return DB::transaction(function () use ($shipment, $reason) {
            $shipment->loadMissing(['destinations', 'items']);

            $history = ShipmentHistory::create([
                'shipment_row_id'             => $shipment->id,
                'shipment_id'                 => $shipment->shipment_id,
                'created_by'                  => $shipment->created_by,
                'approved_by'                 => $shipment->approved_by,
                'assigned_driver_id'          => $shipment->assigned_driver_id,
                'category_id'                 => $shipment->category_id,
                'vehicle_type_id'             => $shipment->vehicle_type_id,
                'division_id'                 => $shipment->division_id,
                'tugas_pengiriman_id'         => $shipment->tugas_pengiriman_id,
                'approved_at'                 => $shipment->approved_at,
                'status'                      => $shipment->status,
                'notes'                       => $shipment->notes,
                'courier_notes'               => $shipment->courier_notes,
                'priority'                    => $shipment->priority,
                'deadline'                    => $shipment->deadline,
                'deadline_locked'             => $shipment->deadline_locked,
                'scheduled_delivery_datetime' => $shipment->scheduled_delivery_datetime,
                'surat_pengantar_kerja'       => $shipment->surat_pengantar_kerja,
                'attachment_path'             => $shipment->attachment_path,
                'shipping_cost'               => $shipment->shipping_cost,
                'vehicle_used'                => $shipment->vehicle_used,
                'online_tracking_url'         => $shipment->online_tracking_url,
                'completion_photo'            => $shipment->completion_photo,
                'completed_at'                => $shipment->completed_at,
                'completed_by'                => $shipment->completed_by,
                'cancelled_by'                => $shipment->cancelled_by,
                'cancelled_at'                => $shipment->cancelled_at,
                'cancel_reason'               => $shipment->cancel_reason,
                'created_at'                  => $shipment->created_at,
                'updated_at'                  => $shipment->updated_at,
                'destinations_snapshot'       => $shipment->destinations->map(fn ($d) => [
                    'id'               => $d->id,
                    'customer_id'      => $d->customer_id,
                    'receiver_company' => $d->receiver_company,
                    'receiver_name'    => $d->receiver_name,
                    'receiver_contact' => $d->receiver_contact,
                    'delivery_address' => $d->delivery_address,
                    'shipment_note'    => $d->shipment_note,
                    'sequence_order'   => $d->sequence_order,
                    'status'           => $d->status,
                ])->toArray(),
                'items_snapshot' => $shipment->items->map(fn ($i) => [
                    'id'           => $i->id,
                    'no_referensi' => $i->no_referensi,
                    'item_name'    => $i->item_name,
                    'quantity'     => $i->quantity,
                    'description'  => $i->description,
                ])->toArray(),
                'archive_reason' => $reason,
            ]);

            $shipment->update([
                'is_archived' => true,
                'archived_at' => now(),
            ]);

            return $history;
        });
    }

    /**
     * Arsipkan shipments lama secara massal (untuk command artisan).
     * Hanya memproses shipments completed/cancelled yang sudah lebih dari $olderThanDays hari.
     */
    public function archiveBatch(int $olderThanDays = 30, int $chunkSize = 200): array
    {
        $cutoff = now()->subDays($olderThanDays);
        $stats  = ['processed' => 0, 'failed' => 0];

        Shipment::active()
            ->whereIn('status', [Shipment::STATUS_COMPLETED, Shipment::STATUS_CANCELLED])
            ->where('updated_at', '<', $cutoff)
            ->with(['destinations', 'items'])
            ->chunkById($chunkSize, function ($shipments) use (&$stats) {
                foreach ($shipments as $shipment) {
                    try {
                        $this->archive($shipment, $shipment->status);
                        $stats['processed']++;
                    } catch (\Throwable $e) {
                        $stats['failed']++;
                        Log::error('ShipmentArchiveService: gagal arsip', [
                            'shipment_id' => $shipment->shipment_id,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $stats;
    }
}
