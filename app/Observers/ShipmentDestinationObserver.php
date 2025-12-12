<?php

namespace App\Observers;

use App\Models\DestinationStatusHistory;
use App\Models\ShipmentDestination;

class ShipmentDestinationObserver
{
    /**
     * Handle the ShipmentDestination "updating" event.
     * Triggered BEFORE the model is updated.
     */
    public function updating(ShipmentDestination $shipmentDestination): void
    {
        // Cek apakah status berubah
        if ($shipmentDestination->isDirty('status')) {
            $oldStatus = $shipmentDestination->getOriginal('status');
            $newStatus = $shipmentDestination->status;

            // Log perubahan status ke history
            DestinationStatusHistory::create([
                'destination_id' => $shipmentDestination->id,
                'shipment_id' => $shipmentDestination->shipment_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => auth()->id(),
                'note' => "Status changed from {$oldStatus} to {$newStatus}",
                'changed_at' => now(),
            ]);

            \Log::info('Destination status changed', [
                'destination_id' => $shipmentDestination->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => auth()->id(),
            ]);
        }
    }
}
