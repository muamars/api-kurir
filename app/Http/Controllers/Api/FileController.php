<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

class FileController extends Controller
{
    public function uploadSpj(Request $request, Shipment $shipment): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'spj_file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check authorization
        if (! $request->user()->hasRole('Admin') && $shipment->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $file = $request->file('spj_file');
            $filename = 'spj_'.$shipment->shipment_id.'_'.time().'.'.$file->getClientOriginalExtension();

            // Delete old SPJ file if exists
            if ($shipment->surat_pengantar_kerja) {
                Storage::disk('public')->delete($shipment->surat_pengantar_kerja);
            }

            $path = $file->storeAs('spj-documents', $filename, 'public');

            $shipment->update([
                'surat_pengantar_kerja' => $path,
            ]);

            return response()->json([
                'message' => 'SPJ document uploaded successfully',
                'data' => [
                    'file_path' => $path,
                    'file_url' => Storage::disk('public')->url($path),
                    'file_name' => $filename,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload SPJ document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadSpj(Shipment $shipment): JsonResponse
    {
        if (! $shipment->surat_pengantar_kerja || ! Storage::disk('public')->exists($shipment->surat_pengantar_kerja)) {
            return response()->json([
                'message' => 'SPJ document not found',
            ], 404);
        }

        $filePath = Storage::disk('public')->path($shipment->surat_pengantar_kerja);

        return response()->download($filePath);
    }

    public function downloadShipmentPhotos(Request $request, Shipment $shipment): JsonResponse
    {
        $progress = $shipment->progress()->whereNotNull('photo_url')->get();

        if ($progress->isEmpty()) {
            return response()->json([
                'message' => 'No photos found for this shipment',
            ], 404);
        }

        try {
            $zipFileName = "shipment_{$shipment->shipment_id}_photos.zip";
            $zipPath = storage_path('app/temp/'.$zipFileName);

            // Create temp directory if not exists
            if (! file_exists(dirname($zipPath))) {
                mkdir(dirname($zipPath), 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                foreach ($progress as $index => $prog) {
                    if ($prog->photo_url && Storage::disk('public')->exists($prog->photo_url)) {
                        $photoPath = Storage::disk('public')->path($prog->photo_url);
                        $photoName = "destination_{$prog->destination_id}_".($index + 1).'_'.basename($prog->photo_url);
                        $zip->addFile($photoPath, $photoName);
                    }

                    if ($prog->received_photo_url && Storage::disk('public')->exists($prog->received_photo_url)) {
                        $receivedPhotoPath = Storage::disk('public')->path($prog->received_photo_url);
                        $receivedPhotoName = "received_destination_{$prog->destination_id}_".($index + 1).'_'.basename($prog->received_photo_url);
                        $zip->addFile($receivedPhotoPath, $receivedPhotoName);
                    }
                }
                $zip->close();

                return response()->download($zipPath)->deleteFileAfterSend(true);
            } else {
                return response()->json([
                    'message' => 'Failed to create zip file',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to download photos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getFileInfo(Request $request, Shipment $shipment): JsonResponse
    {
        $data = [
            'spj_document' => null,
            'photos' => [],
            'total_photos' => 0,
        ];

        // SPJ Document info
        if ($shipment->surat_pengantar_kerja && Storage::disk('public')->exists($shipment->surat_pengantar_kerja)) {
            $data['spj_document'] = [
                'file_path' => $shipment->surat_pengantar_kerja,
                'file_url' => Storage::disk('public')->url($shipment->surat_pengantar_kerja),
                'file_name' => basename($shipment->surat_pengantar_kerja),
                'file_size' => Storage::disk('public')->size($shipment->surat_pengantar_kerja),
                'uploaded_at' => $shipment->updated_at->format('Y-m-d H:i:s'),
            ];
        }

        // Photos info
        $progress = $shipment->progress()->whereNotNull('photo_url')->with('destination')->get();

        foreach ($progress as $prog) {
            $photoData = [
                'destination_id' => $prog->destination_id,
                'destination_name' => $prog->destination->receiver_name,
                'progress_time' => $prog->progress_time->format('Y-m-d H:i:s'),
                'status' => $prog->status,
                'photos' => [],
            ];

            if ($prog->photo_url && Storage::disk('public')->exists($prog->photo_url)) {
                $photoData['photos'][] = [
                    'type' => 'delivery_photo',
                    'url' => Storage::disk('public')->url($prog->photo_url),
                    'thumbnail_url' => $prog->photo_thumbnail ? Storage::disk('public')->url($prog->photo_thumbnail) : null,
                    'file_size' => Storage::disk('public')->size($prog->photo_url),
                ];
            }

            if ($prog->received_photo_url && Storage::disk('public')->exists($prog->received_photo_url)) {
                $photoData['photos'][] = [
                    'type' => 'received_photo',
                    'url' => Storage::disk('public')->url($prog->received_photo_url),
                    'file_size' => Storage::disk('public')->size($prog->received_photo_url),
                ];
            }

            if (! empty($photoData['photos'])) {
                $data['photos'][] = $photoData;
                $data['total_photos'] += count($photoData['photos']);
            }
        }

        return response()->json([
            'data' => $data,
        ]);
    }
}
