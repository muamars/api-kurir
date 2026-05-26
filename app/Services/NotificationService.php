<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Shipment;
use App\Models\User;

class NotificationService
{
    public function shipmentCreated(Shipment $shipment): void
    {
        $creatorName = $shipment->creator?->name ?? 'Unknown';

        $admins = User::role('Admin')->where('is_active', true)->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'shipment_created',
                'title' => 'Permintaan Pengiriman Baru',
                'message' => "Pengiriman baru {$shipment->shipment_id} dibuat oleh {$creatorName}",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'creator' => $creatorName,
                    'priority' => $shipment->priority,
                ],
            ]);
        }
    }

    public function shipmentAssigned(Shipment $shipment): void
    {
        if ($shipment->driver) {
            Notification::create([
                'user_id' => $shipment->assigned_driver_id,
                'type' => 'shipment_assigned',
                'title' => 'Penugasan Pengiriman Baru',
                'message' => "Kamu mendapat tugas baru untuk mengantarkan pengiriman {$shipment->shipment_id}",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'priority' => $shipment->priority,
                    'destinations_count' => $shipment->destinations->count(),
                ],
            ]);
        }

        if ($shipment->created_by && $shipment->driver) {
            $driverName  = $shipment->driver->name;
            $driverPhone = $shipment->driver->phone ?? '-';
            Notification::create([
                'user_id' => $shipment->created_by,
                'type' => 'driver_assigned',
                'title' => 'Kurir Ditugaskan',
                'message' => "Kurir {$driverName} telah ditugaskan untuk pengiriman {$shipment->shipment_id}",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'driver' => $driverName,
                    'driver_phone' => $driverPhone,
                ],
            ]);
        }
    }

    public function shipmentPending(Shipment $shipment): void
    {
        if ($shipment->driver) {
            Notification::create([
                'user_id' => $shipment->assigned_driver_id,
                'type' => 'shipment_pending',
                'title' => 'Penugasan Pengiriman (Menunggu Persetujuan)',
                'message' => "Kamu ditugaskan untuk pengiriman {$shipment->shipment_id} — menunggu persetujuan admin",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'priority' => $shipment->priority,
                    'destinations_count' => $shipment->destinations->count(),
                    'deadline' => $shipment->scheduled_delivery_datetime?->format('Y-m-d'),
                ],
            ]);
        }

        $message = $shipment->driver
            ? "Kurir {$shipment->driver->name} telah ditugaskan untuk pengiriman {$shipment->shipment_id} (menunggu persetujuan)"
            : "Pengiriman {$shipment->shipment_id} sedang menunggu persetujuan admin";

        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'driver_pending',
            'title' => 'Pengiriman Menunggu Persetujuan',
            'message' => $message,
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'driver' => $shipment->driver?->name,
                'driver_phone' => $shipment->driver?->phone,
                'deadline' => $shipment->scheduled_delivery_datetime?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function deliveryStarted(Shipment $shipment): void
    {
        $driverName = $shipment->driver?->name ?? 'Unknown';

        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'delivery_started',
            'title' => 'Pengiriman Dimulai',
            'message' => "Pengiriman {$shipment->shipment_id} telah dimulai oleh kurir {$driverName}",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'driver' => $driverName,
            ],
        ]);
    }

    public function deliveryCompleted(Shipment $shipment): void
    {
        $driverName = $shipment->driver?->name ?? 'Unknown';

        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'delivery_completed',
            'title' => 'Pengiriman Selesai',
            'message' => "Pengiriman {$shipment->shipment_id} telah berhasil diantarkan",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'driver' => $driverName,
                'completed_at' => now()->format('Y-m-d H:i:s'),
            ],
        ]);

        $admins = User::role('Admin')->where('is_active', true)->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'delivery_completed',
                'title' => 'Pengiriman Selesai',
                'message' => "Pengiriman {$shipment->shipment_id} selesai diantarkan oleh kurir {$driverName}",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'driver' => $driverName,
                ],
            ]);
        }
    }

    public function destinationDelivered(Shipment $shipment, $destination, $progress): void
    {
        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'destination_delivered',
            'title' => 'Paket Diterima di Tujuan',
            'message' => "Paket telah diterima oleh {$destination->receiver_name} untuk pengiriman {$shipment->shipment_id}",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'destination_id' => $destination->id,
                'receiver_name' => $destination->receiver_name,
                'delivery_address' => $destination->delivery_address,
                'delivered_at' => $progress->progress_time->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function shipmentCancelled(Shipment $shipment): void
    {
        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'shipment_cancelled',
            'title' => 'Pengiriman Dibatalkan',
            'message' => "Pengiriman {$shipment->shipment_id} telah dibatalkan",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'cancelled_by' => $shipment->cancelled_by,
            ],
        ]);

        if ($shipment->assigned_driver_id) {
            Notification::create([
                'user_id' => $shipment->assigned_driver_id,
                'type' => 'shipment_cancelled_driver',
                'title' => 'Penugasan Dibatalkan',
                'message' => "Penugasan kamu untuk pengiriman {$shipment->shipment_id} telah dibatalkan oleh admin",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                ],
            ]);
        }

        $admins = User::role('Admin')->where('is_active', true)->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'shipment_cancelled_admin',
                'title' => 'Pengiriman Dibatalkan',
                'message' => "Pengiriman {$shipment->shipment_id} telah dibatalkan",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'cancelled_by' => $shipment->cancelled_by,
                ],
            ]);
        }
    }

    public function shipmentCompleted(Shipment $shipment): void
    {
        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'shipment_completed',
            'title' => 'Pengiriman Selesai',
            'message' => "Pengiriman {$shipment->shipment_id} telah diselesaikan oleh admin",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'shipping_cost' => $shipment->shipping_cost,
                'vehicle_used' => $shipment->vehicle_used,
                'completed_at' => $shipment->completed_at?->format('Y-m-d H:i:s'),
            ],
        ]);

        if ($shipment->driver) {
            Notification::create([
                'user_id' => $shipment->assigned_driver_id,
                'type' => 'shipment_completed_driver',
                'title' => 'Pengiriman Selesai',
                'message' => "Pengiriman {$shipment->shipment_id} telah ditandai selesai",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'completed_at' => $shipment->completed_at?->format('Y-m-d H:i:s'),
                ],
            ]);
        }
    }

    public function shipmentTakeover(Shipment $shipment, string $reason): void
    {
        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'shipment_takeover',
            'title' => 'Pengiriman Dikembalikan oleh Kurir',
            'message' => "Pengiriman {$shipment->shipment_id} dikembalikan ke admin oleh kurir. Alasan: {$reason}",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'driver' => $shipment->driver?->name,
                'reason' => $reason,
                'takeover_at' => now()->format('Y-m-d H:i:s'),
            ],
        ]);

        $admins = User::role('Admin')->where('is_active', true)->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'shipment_takeover_admin',
                'title' => 'Pengiriman Dikembalikan oleh Kurir',
                'message' => "Pengiriman {$shipment->shipment_id} dikembalikan oleh {$shipment->driver?->name}. Alasan: {$reason}",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'driver' => $shipment->driver?->name,
                    'driver_phone' => $shipment->driver?->phone,
                    'reason' => $reason,
                    'takeover_at' => now()->format('Y-m-d H:i:s'),
                ],
            ]);
        }

        if ($shipment->driver) {
            Notification::create([
                'user_id' => $shipment->driver->id,
                'type' => 'shipment_takeover_driver',
                'title' => 'Pengembalian Berhasil',
                'message' => "Pengiriman {$shipment->shipment_id} berhasil dikembalikan ke admin untuk ditugaskan ulang",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'reason' => $reason,
                ],
            ]);
        }
    }

    public function shipmentAdminTakeover(Shipment $shipment, int $previousDriverId): void
    {
        Notification::create([
            'user_id' => $previousDriverId,
            'type' => 'shipment_admin_takeover',
            'title' => 'Pengiriman Diambil Alih Admin',
            'message' => "Pengiriman {$shipment->shipment_id} telah diambil alih oleh admin dan dikembalikan ke antrean",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'takeover_at' => now()->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
