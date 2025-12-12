<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentPhotoResource;
use App\Models\Shipment;
use App\Models\ShipmentPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class ShipmentPhotoController extends Controller
{
    /**
     * Get all photos for a shipment
     */
    public function index(Shipment $shipment): JsonResponse
    {
        $photos = $shipment->photos()
            ->with('uploader')
            ->orderBy('uploaded_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Shipment photos retrieved successfully',
            'data' => ShipmentPhotoResource::collection($photos),
        ]);
    }

    /**
     * Upload admin photos for shipment (Admin only)
     */
    public function uploadAdminPhotos(Request $request, Shipment $shipment): JsonResponse
    {
        // $this->authorize('approve-shipments');

        $request->validate([
            'photos' => 'required|array|min:1|max:5',
            'photos.*' => 'required|file|image|mimes:jpeg,png,jpg|max:5120',
            'notes' => 'nullable|string|max:500',
        ]);

        $uploadedPhotos = [];

        foreach ($request->file('photos') as $photo) {
            $path = $this->storePhoto($photo, "shipments/{$shipment->id}/admin");

            $photoRecord = ShipmentPhoto::create([
                'shipment_id' => $shipment->id,
                'type' => ShipmentPhoto::TYPE_ADMIN_UPLOAD,
                'photo_url' => $path['original'],
                'photo_thumbnail' => $path['thumbnail'],
                'uploaded_by' => auth()->id(),
                'notes' => $request->notes,
            ]);

            $uploadedPhotos[] = $photoRecord->load('uploader');
        }

        return response()->json([
            'message' => 'Admin photos uploaded successfully',
            'data' => $uploadedPhotos,
        ], 201);
    }

    /**
     * Upload pickup photo (Driver only)
     */
    public function uploadPickupPhoto(Request $request, Shipment $shipment): JsonResponse
    {
        // Check if driver is assigned to this shipment
        if ($shipment->assigned_driver_id !== auth()->id()) {
            return response()->json([
                'message' => 'You are not assigned to this shipment',
            ], 403);
        }

        // Check if shipment is in correct status
        if (! in_array($shipment->status, ['assigned', 'pending'])) {
            return response()->json([
                'message' => 'Shipment must be assigned or pending to upload pickup photo',
            ], 400);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check if pickup photo already exists
        $existingPickup = $shipment->photos()
            ->where('type', ShipmentPhoto::TYPE_PICKUP)
            ->exists();

        if ($existingPickup) {
            return response()->json([
                'message' => 'Pickup photo already uploaded for this shipment',
            ], 400);
        }

        $path = $this->storePhoto($request->file('photo'), "shipments/{$shipment->id}/pickup");

        $photo = ShipmentPhoto::create([
            'shipment_id' => $shipment->id,
            'type' => ShipmentPhoto::TYPE_PICKUP,
            'photo_url' => $path['original'],
            'photo_thumbnail' => $path['thumbnail'],
            'uploaded_by' => auth()->id(),
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Pickup photo uploaded successfully',
            'data' => $photo->load('uploader'),
        ], 201);
    }

    /**
     * Upload delivery photo (Driver only)
     */
    public function uploadDeliveryPhoto(Request $request, Shipment $shipment): JsonResponse
    {
        // Check if driver is assigned to this shipment
        if ($shipment->assigned_driver_id !== auth()->id()) {
            return response()->json([
                'message' => 'You are not assigned to this shipment',
            ], 403);
        }

        // Check if shipment is completed
        if ($shipment->status !== 'completed') {
            return response()->json([
                'message' => 'Shipment must be completed to upload delivery photo',
            ], 400);
        }

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check if delivery photo already exists
        $existingDelivery = $shipment->photos()
            ->where('type', ShipmentPhoto::TYPE_DELIVERY)
            ->exists();

        if ($existingDelivery) {
            return response()->json([
                'message' => 'Delivery photo already uploaded for this shipment',
            ], 400);
        }

        $path = $this->storePhoto($request->file('photo'), "shipments/{$shipment->id}/delivery");

        $photo = ShipmentPhoto::create([
            'shipment_id' => $shipment->id,
            'type' => ShipmentPhoto::TYPE_DELIVERY,
            'photo_url' => $path['original'],
            'photo_thumbnail' => $path['thumbnail'],
            'uploaded_by' => auth()->id(),
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Delivery photo uploaded successfully',
            'data' => $photo->load('uploader'),
        ], 201);
    }

    /**
     * Delete a photo
     */
    public function destroy(Request $request, Shipment $shipment, ShipmentPhoto $photo): JsonResponse
    {
        // Check ownership or admin permission
        if ($photo->uploaded_by !== auth()->id() && ! $request->user()->can('approve-shipments')) {
            return response()->json([
                'message' => 'You can only delete your own photos',
            ], 403);
        }

        // Delete files
        Storage::disk('public')->delete([
            $photo->photo_url,
            $photo->photo_thumbnail,
        ]);

        $photo->delete();

        return response()->json([
            'message' => 'Photo deleted successfully',
        ]);
    }

    /**
     * Store photo with thumbnail generation
     */
    private function storePhoto($file, string $directory): array
    {
        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();

        // Store original
        $originalPath = $file->storeAs($directory.'/originals', $filename, 'public');

        // Ensure thumbnail directory exists
        $thumbnailDir = storage_path('app/public/'.$directory.'/thumbnails');
        if (! file_exists($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        // Create and store thumbnail
        $manager = ImageManager::gd();
        $image = $manager->read($file);
        $thumbnailPath = $directory.'/thumbnails/'.$filename;
        $image->cover(300, 300)->save(storage_path('app/public/'.$thumbnailPath));

        return [
            'original' => $originalPath,
            'thumbnail' => $thumbnailPath,
        ];
    }
}
