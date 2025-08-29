<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentDestination;
use App\Models\ShipmentProgress;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ShipmentProgressController extends Controller
{
    public function updateProgress(Request $request, Shipment $shipment, ShipmentDestination $destination): JsonResponse
    {
        if ($shipment->assigned_driver_id !== auth()->id()) {
            return response()->json([
                'message' => 'You are not assigned to this shipment'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:arrived,delivered,failed',
            'photo' => 'required|image|max:4096', // 4MB max
            'note' => 'nullable|string',
            'receiver_name' => 'required_if:status,delivered|string',
            'received_photo' => 'nullable|image|max:4096',
        ]);

        try {
            // Handle photo upload
            $photoPath = null;
            $thumbnailPath = null;

            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();

                // Store original photo
                $photoPath = $photo->storeAs('shipment-photos', $filename, 'public');

                // Create thumbnail
                $thumbnailFilename = 'thumb_' . $filename;
                $manager = new ImageManager(new Driver());
                $image = $manager->read($photo);
                $image->resize(300, 300);

                $thumbnailPath = 'shipment-photos/' . $thumbnailFilename;
                Storage::disk('public')->put($thumbnailPath, $image->encode());
            }

            // Handle received photo
            $receivedPhotoPath = null;
            if ($request->hasFile('received_photo')) {
                $receivedPhoto = $request->file('received_photo');
                $receivedFilename = 'received_' . time() . '_' . uniqid() . '.' . $receivedPhoto->getClientOriginalExtension();
                $receivedPhotoPath = $receivedPhoto->storeAs('shipment-photos', $receivedFilename, 'public');
            }

            // Create progress record
            $progress = ShipmentProgress::create([
                'shipment_id' => $shipment->id,
                'destination_id' => $destination->id,
                'driver_id' => auth()->id(),
                'status' => $request->status,
                'progress_time' => now(),
                'photo_url' => $photoPath,
                'photo_thumbnail' => $thumbnailPath,
                'note' => $request->note,
                'receiver_name' => $request->receiver_name,
                'received_photo_url' => $receivedPhotoPath,
            ]);

            // Update destination status
            $destination->update(['status' => $request->status]);

            // Send notification for destination delivery
            if ($request->status === 'delivered') {
                app(NotificationService::class)->destinationDelivered(
                    $shipment->load(['creator', 'driver']),
                    $destination,
                    $progress
                );
            }

            // Check if all destinations are completed
            $allDestinationsCompleted = $shipment->destinations()
                ->where('status', '!=', 'completed')
                ->count() === 0;

            if ($allDestinationsCompleted) {
                $shipment->update(['status' => 'completed']);

                // Send completion notification
                app(NotificationService::class)->deliveryCompleted($shipment->load(['creator', 'driver']));
            }

            return response()->json([
                'message' => 'Progress updated successfully',
                'data' => $progress->load(['destination', 'driver'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getProgress(Shipment $shipment): JsonResponse
    {
        $progress = $shipment->progress()
            ->with(['destination', 'driver'])
            ->orderBy('progress_time', 'desc')
            ->get();

        return response()->json([
            'data' => $progress
        ]);
    }

    public function getDriverHistory(Request $request): JsonResponse
    {
        $driverId = $request->user()->id;

        $history = ShipmentProgress::with(['shipment', 'destination'])
            ->where('driver_id', $driverId)
            ->orderBy('progress_time', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $history
        ]);
    }
}
