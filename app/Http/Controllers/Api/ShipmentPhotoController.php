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
     * Store photo with compression and thumbnail generation
     */
    private function storePhoto($file, string $directory): array
    {
        $filename = time().'_'.uniqid().'.jpg'; // Force JPG for better compression

        // Ensure directories exist
        $originalDir = storage_path('app/public/'.$directory.'/originals');
        $thumbnailDir = storage_path('app/public/'.$directory.'/thumbnails');
        
        if (!file_exists($originalDir)) {
            mkdir($originalDir, 0755, true);
        }
        if (!file_exists($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        $manager = ImageManager::gd();
        $image = $manager->read($file);

        // Get original dimensions for smart compression
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Compress original image with smart quality based on size
        $originalPath = $directory.'/originals/'.$filename;
        $compressedImage = $this->compressImage($image, $originalWidth, $originalHeight);
        $compressedImage->toJpeg($this->getCompressionQuality($originalWidth, $originalHeight))
                       ->save(storage_path('app/public/'.$originalPath));

        // Create and store thumbnail with quality 70%
        $thumbnailPath = $directory.'/thumbnails/'.$filename;
        $image->cover(300, 300)->toJpeg(70)->save(storage_path('app/public/'.$thumbnailPath));

        return [
            'original' => $originalPath,
            'thumbnail' => $thumbnailPath,
        ];
    }

    /**
     * Compress image based on dimensions
     */
    private function compressImage($image, int $width, int $height)
    {
        // If image is too large, resize it first
        $maxWidth = 1920;
        $maxHeight = 1920;

        if ($width > $maxWidth || $height > $maxHeight) {
            // Calculate new dimensions maintaining aspect ratio
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
            
            return $image->resize($newWidth, $newHeight);
        }

        return $image;
    }

    /**
     * Get compression quality based on image dimensions
     */
    private function getCompressionQuality(int $width, int $height): int
    {
        $pixels = $width * $height;

        // Higher resolution = lower quality for better compression
        if ($pixels > 2000000) { // > 2MP
            return 70;
        } elseif ($pixels > 1000000) { // > 1MP
            return 75;
        } elseif ($pixels > 500000) { // > 0.5MP
            return 80;
        } else {
            return 85; // Small images get higher quality
        }
    }
}
