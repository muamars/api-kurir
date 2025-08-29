<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Shipment;
use App\Models\User;

class NotificationService
{
    public function shipmentCreated(Shipment $shipment): void
    {
        // Notify all admins about new shipment
        $admins = User::role('Admin')->where('is_active', true)->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'shipment_created',
                'title' => 'New Shipment Request',
                'message' => "New shipment {$shipment->shipment_id} created by {$shipment->creator->name}",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'creator' => $shipment->creator->name,
                    'priority' => $shipment->priority,
                ]
            ]);
        }
    }

    public function shipmentApproved(Shipment $shipment): void
    {
        // Notify creator about approval
        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'shipment_approved',
            'title' => 'Shipment Approved',
            'message' => "Your shipment {$shipment->shipment_id} has been approved by {$shipment->approver->name}",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'approver' => $shipment->approver->name,
            ]
        ]);
    }

    public function shipmentAssigned(Shipment $shipment): void
    {
        // Notify driver about assignment
        if ($shipment->driver) {
            Notification::create([
                'user_id' => $shipment->assigned_driver_id,
                'type' => 'shipment_assigned',
                'title' => 'New Delivery Assignment',
                'message' => "You have been assigned to deliver shipment {$shipment->shipment_id}",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'priority' => $shipment->priority,
                    'destinations_count' => $shipment->destinations->count(),
                ]
            ]);
        }

        // Notify creator about driver assignment
        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'driver_assigned',
            'title' => 'Driver Assigned',
            'message' => "Driver {$shipment->driver->name} has been assigned to your shipment {$shipment->shipment_id}",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'driver' => $shipment->driver->name,
                'driver_phone' => $shipment->driver->phone,
            ]
        ]);
    }

    public function deliveryStarted(Shipment $shipment): void
    {
        // Notify creator about delivery start
        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'delivery_started',
            'title' => 'Delivery Started',
            'message' => "Delivery for shipment {$shipment->shipment_id} has started",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'driver' => $shipment->driver->name,
            ]
        ]);
    }

    public function deliveryCompleted(Shipment $shipment): void
    {
        // Notify creator about completion
        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'delivery_completed',
            'title' => 'Delivery Completed',
            'message' => "Shipment {$shipment->shipment_id} has been successfully delivered",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'driver' => $shipment->driver->name,
                'completed_at' => now()->format('Y-m-d H:i:s'),
            ]
        ]);

        // Notify admins about completion
        $admins = User::role('Admin')->where('is_active', true)->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'delivery_completed',
                'title' => 'Delivery Completed',
                'message' => "Shipment {$shipment->shipment_id} completed by {$shipment->driver->name}",
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->shipment_id,
                    'driver' => $shipment->driver->name,
                ]
            ]);
        }
    }

    public function destinationDelivered(Shipment $shipment, $destination, $progress): void
    {
        // Notify creator about destination delivery
        Notification::create([
            'user_id' => $shipment->created_by,
            'type' => 'destination_delivered',
            'title' => 'Destination Delivered',
            'message' => "Package delivered to {$destination->receiver_name} for shipment {$shipment->shipment_id}",
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_id,
                'destination_id' => $destination->id,
                'receiver_name' => $destination->receiver_name,
                'delivery_address' => $destination->delivery_address,
                'delivered_at' => $progress->progress_time->format('Y-m-d H:i:s'),
            ]
        ]);
    }
}
