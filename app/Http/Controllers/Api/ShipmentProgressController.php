<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentDestination;
use App\Models\ShipmentProgress;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ShipmentProgressController extends Controller
{
    public function updateProgress(Request $request, $shipmentId, $destinationId): JsonResponse
    {
        // Cari shipment dan destination dengan validasi yang lebih baik
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) {
            return response()->json([
                'message' => 'Shipment tidak ditemukan',
                'shipment_id' => $shipmentId,
            ], 404);
        }

        $destination = ShipmentDestination::find($destinationId);
        if (!$destination) {
            return response()->json([
                'message' => 'Destination tidak ditemukan',
                'destination_id' => $destinationId,
            ], 404);
        }

        // Log request untuk debug
        \Log::info('Update Progress Request', [
            'shipment_id' => $shipment->id,
            'shipment_status_before' => $shipment->status,
            'destination_id' => $destination->id,
            'destination_status_before' => $destination->status,
            'requested_status' => $request->status,
            'has_photo' => $request->hasFile('photo'),
            'user_id' => auth()->id(),
        ]);

        // Validasi: Pastikan destination milik shipment ini
        if ($destination->shipment_id !== $shipment->id) {
            return response()->json([
                'message' => 'Destination tidak milik shipment ini',
                'shipment_id' => $shipment->id,
                'destination_shipment_id' => $destination->shipment_id,
            ], 400);
        }

        // Validasi: Driver assignment
        if ($shipment->assigned_driver_id !== auth()->id()) {
            return response()->json([
                'message' => 'You are not assigned to this shipment',
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:picked,in_progress,arrived,delivered,returning,finished,takeover,failed',
            'photo' => 'nullable|image|max:5120', // 5MB max
            'note' => 'nullable|string',
            'receiver_name' => 'required_if:status,delivered|string',
            'received_photo' => 'nullable|image|max:5120',
            'takeover_reason' => 'required_if:status,takeover|string',
        ]);

        // Validasi: Status flow yang benar - UPDATED untuk takeover rules
        $validStatusTransitions = [
            'pending' => ['picked', 'in_progress', 'takeover'], // ✅ NEW: Takeover dari pending
            'picked' => ['in_progress', 'takeover'],
            'in_progress' => ['arrived', 'delivered', 'takeover', 'failed'], // ✅ Takeover dari in_progress
            'arrived' => ['delivered', 'failed'], // ❌ REMOVED: Tidak bisa takeover dari arrived
            'delivered' => ['returning'], // ❌ REMOVED: Tidak bisa takeover dari delivered
            'returning' => ['finished'],
            'finished' => [], // Status final
            'takeover' => [], // Status final - akan di-reset ke pending otomatis
            'failed' => ['picked', 'in_progress'], // ✅ NEW: Setelah failed, bisa langsung in_progress
        ];

        $currentStatus = $destination->status;
        $newStatus = $request->status;

        // Cek apakah transisi status valid
        if (!isset($validStatusTransitions[$currentStatus]) || 
            !in_array($newStatus, $validStatusTransitions[$currentStatus])) {
            return response()->json([
                'message' => 'Invalid status transition',
                'current_status' => $currentStatus,
                'current_status_label' => $this->getStatusLabel($currentStatus),
                'requested_status' => $newStatus,
                'requested_status_label' => $this->getStatusLabel($newStatus),
                'allowed_next_statuses' => $validStatusTransitions[$currentStatus] ?? [],
                'error' => 'Status tidak boleh lompat. Harus berurutan sesuai flow.',
            ], 400);
        }

        // 🔑 NEW RULES ENGINE - Multi-Package Workflow Logic
        \Log::info('Checking Multi-Package Validation', [
            'new_status' => $newStatus,
            'should_validate' => in_array($newStatus, ['returning', 'finished']),
        ]);
        
        if (in_array($newStatus, ['returning', 'finished'])) {
            \Log::info('Calling validateMultiPackageWorkflow', [
                'shipment_id' => $shipment->id,
                'destination_id' => $destination->id,
                'new_status' => $newStatus,
            ]);
            
            $multiPackageValidation = $this->validateMultiPackageWorkflow($shipment, $destination, $newStatus);
            
            \Log::info('Multi-Package Validation Result', [
                'allowed' => $multiPackageValidation['allowed'],
                'message' => $multiPackageValidation['message'],
                'rule' => $multiPackageValidation['rule'],
            ]);
            
            if (!$multiPackageValidation['allowed']) {
                return response()->json([
                    'message' => $multiPackageValidation['message'],
                    'rule' => $multiPackageValidation['rule'],
                    'current_package_info' => $multiPackageValidation['package_info'],
                    'workflow_explanation' => $multiPackageValidation['explanation'],
                ], 400);
            }
        }

        // Validasi: Pickup atau langsung kirim bisa dilakukan saat shipment status = assigned atau in_progress
        if (in_array($request->status, ['picked', 'in_progress']) && ! in_array($shipment->status, ['assigned', 'in_progress'])) {
            return response()->json([
                'message' => 'Pickup or direct delivery can only be done when shipment is assigned or in progress',
                'current_shipment_status' => $shipment->status,
                'allowed_shipment_statuses' => ['assigned', 'in_progress'],
            ], 400);
        }

        // Validasi: Takeover bisa dilakukan dari status picked atau in_progress
        if ($request->status === 'takeover') {
            if (empty($request->takeover_reason)) {
                return response()->json([
                    'message' => 'Alasan takeover wajib diisi',
                ], 400);
            }
        }

        try {
            // === Handle photo upload ===
            $photoPath = null;
            $thumbnailPath = null;

            if ($request->hasFile('photo')) {
                try {
                    $photo = $request->file('photo');
                    $filename = time().'_'.uniqid().'.jpg'; // Force JPG for better compression

                    // Use compression method
                    $compressedPaths = $this->storeCompressedPhoto($photo, 'shipment-photos', $filename);
                    $photoPath = $compressedPaths['original'];
                    $thumbnailPath = $compressedPaths['thumbnail'];
                } catch (\Exception $e) {
                    \Log::error('Photo upload failed', [
                        'error' => $e->getMessage(),
                        'file' => $photo->getClientOriginalName() ?? 'unknown',
                    ]);
                    throw new \Exception('Failed to upload photo: '.$e->getMessage());
                }
            }

            // === Handle received photo (optional) ===
            $receivedPhotoPath = null;
            if ($request->hasFile('received_photo')) {
                try {
                    $receivedPhoto = $request->file('received_photo');
                    $receivedFilename = 'received_'.time().'_'.uniqid().'.jpg';
                    
                    $compressedReceivedPaths = $this->storeCompressedPhoto($receivedPhoto, 'shipment-photos', $receivedFilename);
                    $receivedPhotoPath = $compressedReceivedPaths['original'];
                } catch (\Exception $e) {
                    \Log::error('Received photo upload failed', [
                        'error' => $e->getMessage(),
                    ]);
                    throw new \Exception('Failed to upload received photo: '.$e->getMessage());
                }
            }

            // === Simpan progress ===
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

            // === Update status destinasi dengan validasi flow ===
            $this->updateDestinationStatus($destination, $request->status);

            // ✅ NEW: Auto-update shipment status based on destination progress
            $this->updateShipmentStatusBasedOnDestinations($shipment);

            // === Handle status DELIVERED: kirim notifikasi ===
            if ($request->status === 'delivered') {
                try {
                    app(NotificationService::class)->destinationDelivered(
                        $shipment->load(['creator', 'driver']),
                        $destination,
                        $progress
                    );
                } catch (\Exception $e) {
                    \Log::warning('Notification failed but progress saved', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // === Handle status TAKEOVER: kembalikan ke admin ===
            if ($request->status === 'takeover') {
                \Log::info('Processing takeover', [
                    'shipment_id' => $shipment->id,
                    'destination_id' => $destination->id,
                    'takeover_reason' => $request->takeover_reason ?? $request->note,
                    'driver_id' => auth()->id(),
                ]);

                // ✅ NEW: Remove shipment from current bulk assignment
                $this->removeShipmentFromBulkAssignment($shipment->id, auth()->id());

                // Update shipment: kembali ke pending, unassign driver
                $shipment->update([
                    'status' => 'pending',
                    'assigned_driver_id' => null,
                ]);

                // PENTING: Reset semua destination yang belum selesai kembali ke pending
                // agar driver baru bisa pickup lagi (updated untuk flow baru)
                $resetCount = $shipment->destinations()
                    ->whereNotIn('status', ['returning', 'finished']) // ✅ UPDATED: Tidak reset yang sudah returning/finished
                    ->update(['status' => 'pending']);

                \Log::info('Takeover destinations reset', [
                    'shipment_id' => $shipment->id,
                    'reset_count' => $resetCount,
                ]);

                try {
                    app(NotificationService::class)->shipmentTakeover(
                        $shipment->load(['creator', 'driver']),
                        $request->takeover_reason ?? $request->note
                    );
                } catch (\Exception $e) {
                    \Log::warning('Takeover notification failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // === Handle status FINISHED: complete shipment jika semua finished ===
            if ($request->status === 'finished') {
                \Log::info('Processing FINISHED status', [
                    'shipment_id' => $shipment->id,
                    'destination_id' => $destination->id,
                    'driver_id' => auth()->id(),
                ]);

                $allDestinationsFinished = $shipment->destinations()
                    ->where('status', 'finished')
                    ->count() === $shipment->destinations()->count();

                \Log::info('Checking if all destinations finished', [
                    'shipment_id' => $shipment->id,
                    'finished_count' => $shipment->destinations()->where('status', 'finished')->count(),
                    'total_count' => $shipment->destinations()->count(),
                    'all_finished' => $allDestinationsFinished,
                ]);

                if ($allDestinationsFinished) {
                    $oldShipmentStatus = $shipment->status;
                    $shipment->update(['status' => 'completed']);
                    \Log::info('Shipment marked as completed', [
                        'shipment_id' => $shipment->id,
                        'old_status' => $oldShipmentStatus,
                        'new_status' => 'completed',
                        'reason' => 'All destinations finished',
                        'driver_id' => auth()->id(),
                    ]);

                    // ✅ NEW: Update semua paket dalam bulk assignment ketika paket terakhir selesai
                    $this->updateBulkAssignmentStatusOnCompletion($shipment, auth()->id());
                }
            }

            return response()->json([
                'message' => 'Progress updated successfully',
                'data' => $progress->load(['destination', 'driver']),
            ]);
        } catch (\Exception $e) {
            \Log::error('Update progress gagal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'shipment_id' => $shipment->id,
                'destination_id' => $destination->id,
                'status' => $request->status ?? null,
            ]);

            return response()->json([
                'message' => 'Failed to update progress',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    // ✅ Helper map status
    private function mapStatus(string $status): string
    {
        return match ($status) {
            'picked' => 'picked',
            'in_progress' => 'in_progress',
            'arrived' => 'arrived',
            'delivered' => 'delivered',
            'completed' => 'completed',
            'returning' => 'returning',
            'finished' => 'finished',
            'takeover' => 'takeover',
            'failed' => 'failed',
            default => 'pending',
        };
    }

    /**
     * Update destination status - setiap status update tercatat dengan benar
     */
    private function updateDestinationStatus(ShipmentDestination $destination, string $newStatus): void
    {
        // Update langsung ke status baru
        // Observer akan otomatis handle history recording
        $destination->update(['status' => $newStatus]);
    }

    /**
     * ✅ NEW: Auto-update shipment status based on destination progress
     * This ensures consistency between bulk assignment and individual shipment endpoints
     */
    private function updateShipmentStatusBasedOnDestinations(Shipment $shipment): void
    {
        // Reload destinations to get latest status
        $shipment->load('destinations');
        $destinations = $shipment->destinations;
        
        if ($destinations->isEmpty()) {
            return;
        }

        $oldShipmentStatus = $shipment->status;
        $newShipmentStatus = $this->determineShipmentStatusFromDestinations($destinations);

        // Only update if status actually changed
        if ($newShipmentStatus !== $oldShipmentStatus) {
            $shipment->update(['status' => $newShipmentStatus]);
            
            \Log::info('Shipment status auto-updated based on destinations', [
                'shipment_id' => $shipment->id,
                'old_status' => $oldShipmentStatus,
                'new_status' => $newShipmentStatus,
                'destination_statuses' => $destinations->pluck('status', 'id')->toArray(),
                'trigger' => 'destination_progress_update',
            ]);
        }
    }

    /**
     * Determine shipment status based on destination statuses
     */
    private function determineShipmentStatusFromDestinations($destinations): string
    {
        $statusCounts = $destinations->countBy('status');
        $totalDestinations = $destinations->count();

        // All destinations finished → shipment completed
        if ($statusCounts->get('finished', 0) === $totalDestinations) {
            return 'completed';
        }

        // Any destination in progress → shipment in progress
        if ($statusCounts->get('in_progress', 0) > 0 || 
            $statusCounts->get('arrived', 0) > 0 || 
            $statusCounts->get('delivered', 0) > 0 || 
            $statusCounts->get('returning', 0) > 0) {
            return 'in_progress';
        }

        // Any destination picked → shipment in progress
        if ($statusCounts->get('picked', 0) > 0) {
            return 'in_progress';
        }

        // All destinations pending and shipment is assigned → keep assigned
        if ($statusCounts->get('pending', 0) === $totalDestinations) {
            return 'assigned'; // Keep as assigned until driver starts
        }

        // Default fallback
        return 'assigned';
    }

    public function getProgress(Shipment $shipment): JsonResponse
    {
        $user = auth()->user();
        
        // Authorization: Admin bisa lihat semua, Kurir hanya yang assigned ke mereka, User hanya yang mereka buat
        if (!$user->hasRole('Admin')) {
            if ($user->hasRole('Kurir')) {
                // Kurir hanya bisa lihat shipment yang assigned ke mereka
                if ($shipment->assigned_driver_id !== $user->id) {
                    return response()->json([
                        'message' => 'You are not assigned to this shipment'
                    ], 403);
                }
            } else {
                // User biasa hanya bisa lihat shipment yang mereka buat
                if ($shipment->created_by !== $user->id) {
                    return response()->json([
                        'message' => 'You can only view your own shipments'
                    ], 403);
                }
            }
        }

        $progress = $shipment->progress()
            ->with(['destination', 'driver'])
            ->orderBy('progress_time', 'desc')
            ->get();

        return response()->json([
            'message' => 'Shipment progress retrieved successfully',
            'data' => $progress,
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
            'data' => $history,
        ]);
    }

    public function getDestinationStatusHistory(Shipment $shipment, ShipmentDestination $destination): JsonResponse
    {
        // Ambil semua history untuk destination ini, urutkan berdasarkan waktu ASC
        $histories = $destination->statusHistories()
            ->with('changedBy')
            ->orderBy('changed_at', 'asc')
            ->get();

        // Status flow yang benar berurutan (simplified flow)
        $statusFlow = ['pending', 'picked', 'in_progress', 'arrived', 'delivered', 'returning', 'finished'];
        
        $validatedHistory = [];
        $statusHistoryMap = [];

        // Buat map history berdasarkan new_status
        foreach ($histories as $history) {
            $statusHistoryMap[$history->new_status] = $history;
        }

        // Validasi dan buat history berurutan sesuai flow yang benar
        for ($i = 0; $i < count($statusFlow) - 1; $i++) {
            $fromStatus = $statusFlow[$i];
            $toStatus = $statusFlow[$i + 1];

            // Cek apakah ada transisi ke toStatus
            if (isset($statusHistoryMap[$toStatus])) {
                $history = $statusHistoryMap[$toStatus];
                
                $validatedHistory[] = [
                    'id' => $history->id,
                    'old_status' => $fromStatus,
                    'new_status' => $toStatus,
                    'old_status_label' => $this->getStatusLabel($fromStatus),
                    'new_status_label' => $this->getStatusLabel($toStatus),
                    'status_description' => $this->getStatusDescription($fromStatus, $toStatus),
                    'note' => $history->note,
                    'changed_at' => $history->changed_at->format('Y-m-d H:i:s'),
                    'changed_by' => $history->changedBy ? [
                        'id' => $history->changedBy->id,
                        'name' => $history->changedBy->name,
                    ] : null,
                ];
            }
        }

        // Reverse array agar urutan dari finished ke pending (seperti endpoint asli)
        $validatedHistory = array_reverse($validatedHistory);

        return response()->json([
            'data' => $validatedHistory,
            'summary' => [
                'total_steps' => count($validatedHistory),
                'current_status' => $destination->status,
                'current_status_label' => $this->getStatusLabel($destination->status),
                'flow_validation' => 'Status flow validated - no jumps allowed',
            ],
        ]);
    }

    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu Pickup',
            'picked' => 'Sudah Dipickup',
            'in_progress' => 'Dalam Perjalanan',
            'arrived' => 'Sampai di Lokasi',
            'delivered' => 'Sudah Diterima',
            'completed' => 'Selesai',
            'returning' => 'Perjalanan Pulang',
            'finished' => 'Sampai di Kantor',
            'takeover' => 'Takeover',
            'failed' => 'Gagal',
            default => ucfirst($status),
        };
    }

    private function getStatusDescription(string $oldStatus, string $newStatus): string
    {
        return match ($oldStatus.'_to_'.$newStatus) {
            'pending_to_picked' => 'Barang sudah di pickup',
            'pending_to_in_progress' => 'Langsung mulai pengiriman (skip pickup)', // ✅ NEW
            'pending_to_takeover' => 'Driver melakukan takeover sebelum pickup', // ✅ NEW
            'picked_to_in_progress' => 'Proses pengiriman',
            'in_progress_to_arrived' => 'Sampai di lokasi',
            'in_progress_to_delivered' => 'Langsung diterima (skip arrived)', // ✅ NEW
            'arrived_to_delivered' => 'Sudah diterima',
            'delivered_to_returning' => 'Arah pulang ke kantor', // ✅ UPDATED: Langsung dari delivered
            'returning_to_finished' => 'Sudah sampai di kantor finish',
            'picked_to_takeover' => 'Driver melakukan takeover setelah pickup',
            'in_progress_to_takeover' => 'Driver melakukan takeover saat dalam perjalanan',
            'in_progress_to_failed' => 'Pengiriman gagal',
            'arrived_to_failed' => 'Gagal menyerahkan barang',
            'failed_to_in_progress' => 'Retry pengiriman langsung (skip pickup)', // ✅ NEW
            default => "Status berubah dari {$this->getStatusLabel($oldStatus)} ke {$this->getStatusLabel($newStatus)}",
        };
    }

    public function getStatusDuration(Shipment $shipment, ShipmentDestination $destination, Request $request): JsonResponse
    {
        $request->validate([
            'from_status' => 'required|string',
            'to_status' => 'required|string',
        ]);

        $fromStatus = $request->from_status;
        $toStatus = $request->to_status;

        // Ambil semua history untuk destination ini, urutkan berdasarkan waktu
        $histories = $destination->statusHistories()
            ->orderBy('changed_at', 'asc')
            ->get();

        // Cari waktu mulai (ketika status berubah KE from_status)
        $startTime = null;
        foreach ($histories as $history) {
            if ($history->new_status === $fromStatus) {
                $startTime = $history->changed_at;
                break;
            }
        }

        // Cari waktu selesai (ketika status berubah KE to_status)
        $endTime = null;
        foreach ($histories as $history) {
            if ($history->new_status === $toStatus && $history->changed_at > $startTime) {
                $endTime = $history->changed_at;
                break;
            }
        }

        if (!$startTime || !$endTime) {
            return response()->json([
                'message' => 'Status transition not found',
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'available_statuses' => $histories->pluck('new_status')->unique()->values(),
            ], 404);
        }

        // Hitung durasi
        $duration = $endTime->diff($startTime);
        $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
        $totalHours = round($totalMinutes / 60, 2);

        return response()->json([
            'data' => [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'from_status_label' => $this->getStatusLabel($fromStatus),
                'to_status_label' => $this->getStatusLabel($toStatus),
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s'),
                'duration' => [
                    'days' => $duration->days,
                    'hours' => $duration->h,
                    'minutes' => $duration->i,
                    'seconds' => $duration->s,
                    'total_minutes' => $totalMinutes,
                    'total_hours' => $totalHours,
                    'human_readable' => $this->formatDuration($duration),
                ],
            ],
        ]);
    }



    public function getAllStatusDurations(Shipment $shipment, ShipmentDestination $destination): JsonResponse
    {
        // Ambil semua history untuk destination ini, urutkan berdasarkan waktu
        $histories = $destination->statusHistories()
            ->orderBy('changed_at', 'asc')
            ->get();

        if ($histories->isEmpty()) {
            return response()->json([
                'message' => 'No status history found',
                'data' => [],
            ]);
        }

        $durations = [];
        $statusFlow = ['pending', 'picked', 'in_progress', 'arrived', 'delivered', 'returning', 'finished'];

        // Buat map waktu untuk setiap status
        $statusTimes = [];
        foreach ($histories as $history) {
            $statusTimes[$history->new_status] = $history->changed_at;
        }

        // Hitung durasi antar status berurutan
        for ($i = 0; $i < count($statusFlow) - 1; $i++) {
            $fromStatus = $statusFlow[$i];
            $toStatus = $statusFlow[$i + 1];

            if (isset($statusTimes[$fromStatus]) && isset($statusTimes[$toStatus])) {
                $startTime = $statusTimes[$fromStatus];
                $endTime = $statusTimes[$toStatus];

                if ($endTime > $startTime) {
                    $duration = $endTime->diff($startTime);
                    $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
                    $totalHours = round($totalMinutes / 60, 2);

                    $durations[] = [
                        'old_status' => $fromStatus,
                        'new_status' => $toStatus,
                        'old_status_label' => $this->getStatusLabel($fromStatus),
                        'new_status_label' => $this->getStatusLabel($toStatus),
                        'status_description' => $this->getStatusDescription($fromStatus, $toStatus),
                        'start_time' => $startTime->format('Y-m-d H:i:s'),
                        'end_time' => $endTime->format('Y-m-d H:i:s'),
                        'duration' => [
                            'days' => $duration->days,
                            'hours' => $duration->h,
                            'minutes' => $duration->i,
                            'seconds' => $duration->s,
                            'total_minutes' => $totalMinutes,
                            'total_hours' => $totalHours,
                            'human_readable' => $this->formatDuration($duration),
                        ],
                    ];
                }
            }
        }

        // Hitung total durasi dari pending sampai finished
        $totalDuration = null;
        if (isset($statusTimes['pending']) && isset($statusTimes['finished'])) {
            $totalStart = $statusTimes['pending'];
            $totalEnd = $statusTimes['finished'];
            $totalDiff = $totalEnd->diff($totalStart);
            $totalMinutes = ($totalDiff->days * 24 * 60) + ($totalDiff->h * 60) + $totalDiff->i;
            $totalHours = round($totalMinutes / 60, 2);

            $totalDuration = [
                'days' => $totalDiff->days,
                'hours' => $totalDiff->h,
                'minutes' => $totalDiff->i,
                'seconds' => $totalDiff->s,
                'total_minutes' => $totalMinutes,
                'total_hours' => $totalHours,
                'human_readable' => $this->formatDuration($totalDiff),
            ];
        }

        return response()->json([
            'data' => [
                'step_durations' => $durations,
                'total_duration' => $totalDuration,
                'summary' => [
                    'total_steps' => count($durations),
                    'available_statuses' => array_keys($statusTimes),
                ],
            ],
        ]);
    }

    private function formatDuration(\DateInterval $duration): string
    {
        $parts = [];
        
        if ($duration->days > 0) {
            $parts[] = $duration->days . ' hari';
        }
        if ($duration->h > 0) {
            $parts[] = $duration->h . ' jam';
        }
        if ($duration->i > 0) {
            $parts[] = $duration->i . ' menit';
        }
        if ($duration->s > 0 && empty($parts)) {
            $parts[] = $duration->s . ' detik';
        }

        return empty($parts) ? '0 detik' : implode(' ', $parts);
    }

    public function getDriverPerformanceReport(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'nullable|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = Shipment::with(['destinations.statusHistories', 'driver'])
            ->whereNotNull('assigned_driver_id');

        // Filter by specific driver or get all drivers
        if ($request->driver_id) {
            $query->where('assigned_driver_id', $request->driver_id);
        }

        // Date range filter
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $shipments = $query->get();

        $driverReports = [];
        $overallStats = [
            'total_drivers' => 0,
            'total_deliveries' => 0,
            'total_locations' => 0,
            'avg_delivery_time' => 0,
        ];

        // Group shipments by driver
        $shipmentsByDriver = $shipments->groupBy('assigned_driver_id');

        foreach ($shipmentsByDriver as $driverId => $driverShipments) {
            $driver = $driverShipments->first()->driver;
            if (!$driver) continue;

            $driverReport = [
                'driver' => [
                    'id' => $driver->id,
                    'name' => $driver->name,
                ],
                'deliveries' => [],
                'summary' => [
                    'total_shipments' => $driverShipments->count(),
                    'total_destinations' => 0,
                    'completed_destinations' => 0,
                    'avg_delivery_time' => 0,
                    'fastest_delivery' => null,
                    'slowest_delivery' => null,
                ],
            ];

            $deliveryTimes = [];
            $totalDestinations = 0;
            $completedDestinations = 0;

            foreach ($driverShipments as $shipment) {
                foreach ($shipment->destinations as $destination) {
                    $totalDestinations++;
                    
                    $deliveryData = $this->analyzeDestinationDeliveryTime($destination);
                    
                    if ($deliveryData) {
                        $completedDestinations++;
                        $deliveryTimes[] = $deliveryData['delivery_time_minutes'];
                        
                        $driverReport['deliveries'][] = [
                            'shipment_id' => $shipment->shipment_id,
                            'location' => $destination->delivery_address,
                            'receiver_name' => $destination->receiver_name,
                            'delivery_time' => $deliveryData['delivery_time_human'],
                            'delivery_time_minutes' => $deliveryData['delivery_time_minutes'],
                            'status' => $destination->status,
                            'delivered_at' => $deliveryData['delivered_at'],
                        ];
                    }
                }
            }

            // Calculate driver summary
            $driverReport['summary']['total_destinations'] = $totalDestinations;
            $driverReport['summary']['completed_destinations'] = $completedDestinations;
            
            if (!empty($deliveryTimes)) {
                $driverReport['summary']['avg_delivery_time'] = round(array_sum($deliveryTimes) / count($deliveryTimes), 2);
                $driverReport['summary']['fastest_delivery'] = min($deliveryTimes) . ' menit';
                $driverReport['summary']['slowest_delivery'] = max($deliveryTimes) . ' menit';
            }

            // Sort deliveries by delivery time (fastest first)
            usort($driverReport['deliveries'], function($a, $b) {
                return $a['delivery_time_minutes'] <=> $b['delivery_time_minutes'];
            });

            $driverReports[] = $driverReport;

            // Update overall stats
            $overallStats['total_deliveries'] += $completedDestinations;
            $overallStats['total_locations'] += $totalDestinations;
        }

        $overallStats['total_drivers'] = count($driverReports);
        
        // Calculate overall average delivery time
        $allDeliveryTimes = [];
        foreach ($driverReports as $report) {
            foreach ($report['deliveries'] as $delivery) {
                $allDeliveryTimes[] = $delivery['delivery_time_minutes'];
            }
        }
        
        if (!empty($allDeliveryTimes)) {
            $overallStats['avg_delivery_time'] = round(array_sum($allDeliveryTimes) / count($allDeliveryTimes), 2);
        }

        return response()->json([
            'data' => [
                'driver_reports' => $driverReports,
                'overall_stats' => $overallStats,
                'report_period' => [
                    'from' => $request->date_from ?? 'Semua waktu',
                    'to' => $request->date_to ?? 'Semua waktu',
                ],
            ],
        ]);
    }

    private function analyzeDestinationDeliveryTime($destination): ?array
    {
        $histories = $destination->statusHistories()
            ->orderBy('changed_at', 'asc')
            ->get();

        if ($histories->isEmpty()) {
            return null;
        }

        // Find pickup time (when status changed to 'picked')
        $pickupTime = null;
        $deliveredTime = null;

        foreach ($histories as $history) {
            if ($history->new_status === 'picked' && !$pickupTime) {
                $pickupTime = $history->changed_at;
            }
            if ($history->new_status === 'delivered') {
                $deliveredTime = $history->changed_at;
                break; // Stop at delivered status
            }
        }

        if (!$pickupTime || !$deliveredTime) {
            return null;
        }

        // Calculate delivery time from pickup to delivered
        $duration = $deliveredTime->diff($pickupTime);
        $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;

        return [
            'pickup_time' => $pickupTime->format('Y-m-d H:i:s'),
            'delivered_at' => $deliveredTime->format('Y-m-d H:i:s'),
            'delivery_time_minutes' => $totalMinutes,
            'delivery_time_human' => $this->formatDuration($duration),
        ];
    }

    public function getDriverRouteReport(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'nullable|exists:users,id',
            'bulk_assignment_id' => 'nullable|exists:bulk_assignments,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = DB::table('bulk_assignments as ba')
            ->join('users as admin', 'ba.admin_id', '=', 'admin.id')
            ->join('users as driver', 'ba.driver_id', '=', 'driver.id')
            ->join('vehicle_types as vt', 'ba.vehicle_type_id', '=', 'vt.id')
            ->select([
                'ba.id as bulk_assignment_id',
                'ba.shipment_count',
                'ba.shipment_ids',
                'ba.assigned_at',
                'admin.name as admin_name',
                'driver.id as driver_id',
                'driver.name as driver_name',
                'vt.name as vehicle_type_name'
            ]);

        // Filter by specific driver
        if ($request->driver_id) {
            $query->where('ba.driver_id', $request->driver_id);
        }

        // Filter by specific bulk assignment (rute)
        if ($request->bulk_assignment_id) {
            $query->where('ba.id', $request->bulk_assignment_id);
        }

        // Date range filter
        if ($request->date_from) {
            $query->whereDate('ba.assigned_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('ba.assigned_at', '<=', $request->date_to);
        }

        $bulkAssignments = $query->orderBy('ba.assigned_at', 'desc')->get();

        $routeReports = [];
        $overallStats = [
            'total_routes' => 0,
            'total_shipments' => 0,
            'completed_routes' => 0,
            'avg_route_completion_time' => 0,
        ];

        foreach ($bulkAssignments as $bulkAssignment) {
            $shipmentIds = json_decode($bulkAssignment->shipment_ids);
            
            // Get shipments with their destinations and progress
            $shipments = Shipment::with(['destinations.statusHistories', 'creator'])
                ->whereIn('id', $shipmentIds)
                ->get();

            $routeData = [
                'route_info' => [
                    'bulk_assignment_id' => $bulkAssignment->bulk_assignment_id,
                    'route_name' => "Rute #{$bulkAssignment->bulk_assignment_id}",
                    'assigned_at' => $bulkAssignment->assigned_at,
                    'admin_name' => $bulkAssignment->admin_name,
                    'driver_name' => $bulkAssignment->driver_name,
                    'vehicle_type' => $bulkAssignment->vehicle_type_name,
                    'total_shipments' => count($shipmentIds),
                ],
                'shipments' => [],
                'route_summary' => [
                    'completed_shipments' => 0,
                    'total_destinations' => 0,
                    'completed_destinations' => 0,
                    'route_start_time' => null,
                    'route_end_time' => null,
                    'total_route_time' => null,
                    'avg_delivery_time_per_shipment' => 0,
                ],
            ];

            $allDeliveryTimes = [];
            $routeStartTime = null;
            $routeEndTime = null;
            $completedShipments = 0;
            $totalDestinations = 0;
            $completedDestinations = 0;

            foreach ($shipments as $shipment) {
                $shipmentData = [
                    'shipment_id' => $shipment->shipment_id,
                    'creator' => $shipment->creator->name,
                    'current_status' => $shipment->status,
                    'destinations' => [],
                    'shipment_timing' => null,
                ];

                $shipmentCompleted = true;
                $shipmentStartTime = null;
                $shipmentEndTime = null;

                foreach ($shipment->destinations as $destination) {
                    $totalDestinations++;
                    
                    $destinationTiming = $this->analyzeRouteDestinationTiming($destination);
                    
                    $destinationData = [
                        'destination_id' => $destination->id,
                        'delivery_address' => $destination->delivery_address,
                        'receiver_name' => $destination->receiver_name,
                        'current_status' => $destination->status,
                        'distance_category' => $this->categorizeDistance($destination->delivery_address),
                        'timing' => $destinationTiming,
                    ];

                    if ($destinationTiming) {
                        $completedDestinations++;
                        $allDeliveryTimes[] = $destinationTiming['delivery_time_minutes'];

                        // Track route start and end times
                        if (!$routeStartTime || $destinationTiming['pickup_time'] < $routeStartTime) {
                            $routeStartTime = $destinationTiming['pickup_time'];
                        }
                        if (!$routeEndTime || $destinationTiming['delivered_at'] > $routeEndTime) {
                            $routeEndTime = $destinationTiming['delivered_at'];
                        }

                        // Track shipment start and end times
                        if (!$shipmentStartTime || $destinationTiming['pickup_time'] < $shipmentStartTime) {
                            $shipmentStartTime = $destinationTiming['pickup_time'];
                        }
                        if (!$shipmentEndTime || $destinationTiming['delivered_at'] > $shipmentEndTime) {
                            $shipmentEndTime = $destinationTiming['delivered_at'];
                        }
                    } else {
                        $shipmentCompleted = false;
                    }

                    $shipmentData['destinations'][] = $destinationData;
                }

                // Calculate shipment timing if completed
                if ($shipmentCompleted && $shipmentStartTime && $shipmentEndTime) {
                    $completedShipments++;
                    $duration = $shipmentEndTime->diff($shipmentStartTime);
                    $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
                    
                    $shipmentData['shipment_timing'] = [
                        'start_time' => $shipmentStartTime->format('Y-m-d H:i:s'),
                        'end_time' => $shipmentEndTime->format('Y-m-d H:i:s'),
                        'total_time_minutes' => $totalMinutes,
                        'total_time_human' => $this->formatDuration($duration),
                    ];
                }

                $routeData['shipments'][] = $shipmentData;
            }

            // Calculate route summary
            $routeData['route_summary']['completed_shipments'] = $completedShipments;
            $routeData['route_summary']['total_destinations'] = $totalDestinations;
            $routeData['route_summary']['completed_destinations'] = $completedDestinations;

            if ($routeStartTime && $routeEndTime) {
                $routeDuration = $routeEndTime->diff($routeStartTime);
                $routeTotalMinutes = ($routeDuration->days * 24 * 60) + ($routeDuration->h * 60) + $routeDuration->i;
                
                $routeData['route_summary']['route_start_time'] = $routeStartTime->format('Y-m-d H:i:s');
                $routeData['route_summary']['route_end_time'] = $routeEndTime->format('Y-m-d H:i:s');
                $routeData['route_summary']['total_route_time'] = [
                    'minutes' => $routeTotalMinutes,
                    'human' => $this->formatDuration($routeDuration),
                ];
            }

            if (!empty($allDeliveryTimes)) {
                $routeData['route_summary']['avg_delivery_time_per_shipment'] = round(array_sum($allDeliveryTimes) / count($allDeliveryTimes), 2);
            }

            $routeReports[] = $routeData;

            // Update overall stats
            $overallStats['total_routes']++;
            $overallStats['total_shipments'] += count($shipmentIds);
            if ($completedShipments === count($shipmentIds)) {
                $overallStats['completed_routes']++;
            }
        }

        // Calculate overall average route completion time
        $allRouteTimes = [];
        foreach ($routeReports as $route) {
            if (isset($route['route_summary']['total_route_time']['minutes'])) {
                $allRouteTimes[] = $route['route_summary']['total_route_time']['minutes'];
            }
        }
        
        if (!empty($allRouteTimes)) {
            $overallStats['avg_route_completion_time'] = round(array_sum($allRouteTimes) / count($allRouteTimes), 2);
        }

        return response()->json([
            'data' => [
                'route_reports' => $routeReports,
                'overall_stats' => $overallStats,
                'report_period' => [
                    'from' => $request->date_from ?? 'Semua waktu',
                    'to' => $request->date_to ?? 'Semua waktu',
                ],
            ],
        ]);
    }

    private function analyzeRouteDestinationTiming($destination): ?array
    {
        $histories = $destination->statusHistories()
            ->orderBy('changed_at', 'asc')
            ->get();

        if ($histories->isEmpty()) {
            return null;
        }

        $pickupTime = null;
        $deliveredTime = null;

        foreach ($histories as $history) {
            if ($history->new_status === 'picked' && !$pickupTime) {
                $pickupTime = $history->changed_at;
            }
            if ($history->new_status === 'delivered') {
                $deliveredTime = $history->changed_at;
                break;
            }
        }

        if (!$pickupTime || !$deliveredTime) {
            return null;
        }

        $duration = $deliveredTime->diff($pickupTime);
        $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;

        return [
            'pickup_time' => $pickupTime,
            'delivered_at' => $deliveredTime,
            'delivery_time_minutes' => $totalMinutes,
            'delivery_time_human' => $this->formatDuration($duration),
        ];
    }

    private function categorizeDistance(string $address): string
    {
        // Simple distance categorization based on address keywords
        $address = strtolower($address);
        
        if (strpos($address, 'jakarta pusat') !== false || strpos($address, 'sudirman') !== false || strpos($address, 'thamrin') !== false) {
            return 'Jarak Dekat';
        } elseif (strpos($address, 'jakarta selatan') !== false || strpos($address, 'jakarta utara') !== false || strpos($address, 'kemang') !== false) {
            return 'Jarak Sedang';
        } elseif (strpos($address, 'bekasi') !== false || strpos($address, 'tangerang') !== false || strpos($address, 'depok') !== false || strpos($address, 'bogor') !== false) {
            return 'Jarak Jauh';
        }
        
        return 'Jarak Tidak Diketahui';
    }

    /**
     * Store compressed photo with thumbnail generation
     */
    private function storeCompressedPhoto($file, string $directory, string $filename): array
    {
        $manager = ImageManager::gd();
        $image = $manager->read($file);

        // Get original dimensions for smart compression
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Compress and store original
        $originalPath = $directory.'/'.$filename;
        $compressedImage = $this->compressProgressImage($image, $originalWidth, $originalHeight);
        $encodedImage = $compressedImage->toJpeg($this->getProgressCompressionQuality($originalWidth, $originalHeight));
        
        Storage::disk('public')->put($originalPath, $encodedImage);

        // Create and store thumbnail
        $thumbnailFilename = 'thumb_'.$filename;
        $thumbnailPath = $directory.'/'.$thumbnailFilename;
        $thumbnailImage = $image->cover(300, 300)->toJpeg(70);
        Storage::disk('public')->put($thumbnailPath, $thumbnailImage);

        return [
            'original' => $originalPath,
            'thumbnail' => $thumbnailPath,
        ];
    }

    /**
     * Compress image based on dimensions for progress photos
     */
    private function compressProgressImage($image, int $width, int $height)
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
     * Get compression quality based on image dimensions for progress photos
     */
    private function getProgressCompressionQuality(int $width, int $height): int
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

    /**
     * 🔑 Multi-Package Workflow Rules Engine
     * 
     * Rule A: Kurir hanya punya 1 paket → Full cycle sampai finished
     * Rule B: Kurir punya > 1 paket → Sequential processing + Paket 1 & 2 mentok di delivered, 
     *         hanya paket terakhir yang bisa returning → finished
     * Rule C: Sequential Processing → Paket A harus selesai dulu sebelum Paket B bisa mulai
     */
    private function validateMultiPackageWorkflow($shipment, $destination, $requestedStatus): array
    {
        // Get bulk assignment for this driver to maintain order
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        
        if ($driver === 'mysql') {
            // MySQL syntax
            $bulkAssignment = DB::table('bulk_assignments')
                ->where('driver_id', auth()->id())
                ->whereRaw('JSON_CONTAINS(shipment_ids, ?)', [json_encode($shipment->id)])
                ->first();
        } else {
            // PostgreSQL syntax
            $bulkAssignment = DB::table('bulk_assignments')
                ->where('driver_id', auth()->id())
                ->whereRaw('shipment_ids::jsonb @> ?', [json_encode($shipment->id)])
                ->first();
        }
        
        // Debug logging
        \Log::info('Multi-Package Workflow Debug', [
            'shipment_id' => $shipment->id,
            'destination_id' => $destination->id,
            'destination_status' => $destination->status,
            'requested_status' => $requestedStatus,
            'bulk_assignment_found' => $bulkAssignment ? true : false,
            'bulk_assignment_id' => $bulkAssignment ? $bulkAssignment->id : null,
            'database_driver' => $driver,
        ]);
        
        if ($bulkAssignment) {
            // Get shipment IDs in the order assigned by admin
            $shipmentIds = json_decode($bulkAssignment->shipment_ids, true);
            
            \Log::info('Bulk Assignment Order', [
                'shipment_ids_order' => $shipmentIds,
                'current_shipment_id' => $shipment->id,
            ]);
            
            // Get all destinations from these shipments in the correct order
            $allDestinations = collect();
            foreach ($shipmentIds as $shipmentId) {
                $bulkShipment = Shipment::with('destinations')
                    ->where('id', $shipmentId)
                    ->where('assigned_driver_id', auth()->id())
                    ->first();
                
                if ($bulkShipment) {
                    foreach ($bulkShipment->destinations as $dest) {
                        $allDestinations->push($dest);
                    }
                }
            }
            
            $totalPackages = $allDestinations->count();
            $currentPackageIndex = $allDestinations->search(function ($dest) use ($destination) {
                return $dest->id === $destination->id;
            });
            
            \Log::info('Package Position Debug', [
                'total_packages' => $totalPackages,
                'current_package_index' => $currentPackageIndex,
                'all_destinations' => $allDestinations->map(function($dest) {
                    return [
                        'id' => $dest->id,
                        'shipment_id' => $dest->shipment_id,
                        'status' => $dest->status,
                        'address' => substr($dest->delivery_address, 0, 30) . '...',
                    ];
                })->toArray()
            ]);
            
            // Rule A: Hanya 1 paket → Full cycle allowed
            if ($totalPackages === 1) {
                return [
                    'allowed' => true,
                    'message' => 'Allowed: Single package workflow',
                    'rule' => 'Rule A: Single package - full cycle allowed',
                    'package_info' => [
                        'total_packages' => $totalPackages,
                        'current_package_position' => 1,
                        'is_last_package' => true,
                    ],
                    'explanation' => 'Kurir hanya punya 1 paket, boleh full cycle sampai finished'
                ];
            }
            
            $isLastPackage = ($currentPackageIndex !== false) && ($currentPackageIndex === $totalPackages - 1);
            
            // 🔑 NEW: Rule C - Sequential Processing Validation
            // Check if trying to start a package while previous packages are not completed
            if (in_array($requestedStatus, ['in_progress', 'arrived', 'delivered']) && $currentPackageIndex !== false) {
                // Check if any previous packages are not yet delivered
                for ($i = 0; $i < $currentPackageIndex; $i++) {
                    $previousPackage = $allDestinations[$i];
                    if (!in_array($previousPackage->status, ['delivered', 'returning', 'finished'])) {
                        return [
                            'allowed' => false,
                            'message' => 'Sequential processing required: Previous package must be completed first',
                            'rule' => 'Rule C: Sequential Processing',
                            'package_info' => [
                                'total_packages' => $totalPackages,
                                'current_package_position' => $currentPackageIndex + 1,
                                'blocking_package_position' => $i + 1,
                                'blocking_package_status' => $previousPackage->status,
                                'blocking_package_status_label' => $this->getStatusLabel($previousPackage->status),
                                'blocking_package_address' => $previousPackage->delivery_address,
                                'is_last_package' => $isLastPackage,
                            ],
                            'explanation' => "Paket " . ($i + 1) . " harus diselesaikan dulu (status: {$this->getStatusLabel($previousPackage->status)}) sebelum Paket " . ($currentPackageIndex + 1) . " bisa dimulai"
                        ];
                    }
                }
            }
            
            // Rule B: Multi-package workflow - hanya untuk returning/finished
            if (in_array($requestedStatus, ['returning', 'finished'])) {
                \Log::info('Checking returning/finished permission', [
                    'is_last_package' => $isLastPackage,
                    'current_package_index' => $currentPackageIndex,
                    'total_packages' => $totalPackages,
                ]);
                
                if ($isLastPackage) {
                    return [
                        'allowed' => true,
                        'message' => 'Allowed: This is the last package',
                        'rule' => 'Rule B: Multi-package - last package can return',
                        'package_info' => [
                            'total_packages' => $totalPackages,
                            'current_package_position' => $currentPackageIndex + 1,
                            'is_last_package' => true,
                        ],
                        'explanation' => 'Ini adalah paket terakhir, boleh returning → finished'
                    ];
                } else {
                    return [
                        'allowed' => false,
                        'message' => 'Not allowed: You still have other packages to deliver',
                        'rule' => 'Rule B: Multi-package - only last package can return',
                        'package_info' => [
                            'total_packages' => $totalPackages,
                            'current_package_position' => $currentPackageIndex + 1,
                            'remaining_packages' => $totalPackages - ($currentPackageIndex + 1),
                            'is_last_package' => false,
                        ],
                        'explanation' => 'Paket 1 & 2 mentok di delivered. Hanya paket terakhir yang bisa returning → finished'
                    ];
                }
            }
            
        } else {
            // Fallback: Single shipment or non-bulk assignment
            return [
                'allowed' => true,
                'message' => 'Allowed: Single shipment or non-bulk assignment',
                'rule' => 'Rule A: Single shipment',
                'package_info' => [
                    'total_packages' => 1,
                    'current_package_position' => 1,
                    'is_last_package' => true,
                ],
                'explanation' => 'Shipment tunggal atau bukan bulk assignment, boleh lanjut'
            ];
        }

        // Default allow untuk status lainnya (setelah sequential check)
        return [
            'allowed' => true,
            'message' => 'Allowed: Status transition permitted',
            'rule' => 'Default: Normal status transition',
            'package_info' => [
                'total_packages' => $totalPackages ?? 1,
                'current_package_position' => ($currentPackageIndex ?? 0) + 1,
                'is_last_package' => $isLastPackage ?? true,
            ],
            'explanation' => 'Transisi status normal diizinkan'
        ];
    }

    /**
     * Remove shipment from bulk assignment when takeover occurs
     */
    private function removeShipmentFromBulkAssignment(int $shipmentId, int $driverId): void
    {
        try {
            // Find bulk assignment containing this shipment for this driver
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            
            if ($driver === 'mysql') {
                // MySQL syntax
                $bulkAssignment = DB::table('bulk_assignments')
                    ->where('driver_id', $driverId)
                    ->whereRaw('JSON_CONTAINS(shipment_ids, ?)', [json_encode($shipmentId)])
                    ->first();
            } else {
                // PostgreSQL syntax
                $bulkAssignment = DB::table('bulk_assignments')
                    ->where('driver_id', $driverId)
                    ->whereRaw('shipment_ids::jsonb @> ?', [json_encode($shipmentId)])
                    ->first();
            }

            if ($bulkAssignment) {
                $shipmentIds = json_decode($bulkAssignment->shipment_ids, true);
                
                // Remove the shipment from the array
                $updatedShipmentIds = array_values(array_filter($shipmentIds, function($id) use ($shipmentId) {
                    return $id !== $shipmentId;
                }));

                // Update bulk assignment
                if (empty($updatedShipmentIds)) {
                    // If no shipments left, delete the bulk assignment
                    DB::table('bulk_assignments')->where('id', $bulkAssignment->id)->delete();
                    \Log::info('Bulk assignment deleted (no shipments left)', [
                        'bulk_assignment_id' => $bulkAssignment->id,
                        'removed_shipment_id' => $shipmentId,
                    ]);
                } else {
                    // Update with remaining shipments
                    DB::table('bulk_assignments')
                        ->where('id', $bulkAssignment->id)
                        ->update([
                            'shipment_ids' => json_encode($updatedShipmentIds),
                            'shipment_count' => count($updatedShipmentIds),
                        ]);
                    \Log::info('Shipment removed from bulk assignment', [
                        'bulk_assignment_id' => $bulkAssignment->id,
                        'removed_shipment_id' => $shipmentId,
                        'remaining_shipments' => $updatedShipmentIds,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to remove shipment from bulk assignment', [
                'shipment_id' => $shipmentId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ✅ NEW: Update semua paket dalam bulk assignment ketika paket terakhir selesai
     * Ini memastikan semua paket A, B, C dalam bulk assignment ikut berubah status menjadi completed
     */
    private function updateBulkAssignmentStatusOnCompletion(Shipment $completedShipment, int $driverId): void
    {
        try {
            // Find bulk assignment containing this shipment
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            
            if ($driver === 'mysql') {
                // MySQL syntax
                $bulkAssignment = DB::table('bulk_assignments')
                    ->where('driver_id', $driverId)
                    ->whereRaw('JSON_CONTAINS(shipment_ids, ?)', [json_encode($completedShipment->id)])
                    ->first();
            } else {
                // PostgreSQL syntax
                $bulkAssignment = DB::table('bulk_assignments')
                    ->where('driver_id', $driverId)
                    ->whereRaw('shipment_ids::jsonb @> ?', [json_encode($completedShipment->id)])
                    ->first();
            }

            if (!$bulkAssignment) {
                \Log::info('No bulk assignment found for completed shipment', [
                    'shipment_id' => $completedShipment->id,
                    'driver_id' => $driverId,
                ]);
                return;
            }

            $shipmentIds = json_decode($bulkAssignment->shipment_ids, true);
            
            // Get all shipments in this bulk assignment
            $allBulkShipments = Shipment::with('destinations')
                ->whereIn('id', $shipmentIds)
                ->where('assigned_driver_id', $driverId)
                ->get();

            \Log::info('Checking bulk assignment completion status', [
                'bulk_assignment_id' => $bulkAssignment->id,
                'total_shipments' => $allBulkShipments->count(),
                'shipment_ids' => $shipmentIds,
            ]);

            // Check if this is the last package (paket terakhir) that just finished
            $isLastPackage = $this->isLastPackageInBulkAssignment($completedShipment, $allBulkShipments, $shipmentIds);
            
            if ($isLastPackage) {
                \Log::info('Last package completed - updating all bulk assignment shipments', [
                    'completed_shipment_id' => $completedShipment->id,
                    'bulk_assignment_id' => $bulkAssignment->id,
                ]);

                $updatedCount = 0;
                foreach ($allBulkShipments as $bulkShipment) {
                    // Skip the already completed shipment
                    if ($bulkShipment->id === $completedShipment->id) {
                        continue;
                    }

                    // Update shipment status to completed if not already
                    if ($bulkShipment->status !== 'completed') {
                        $oldStatus = $bulkShipment->status;
                        $bulkShipment->update(['status' => 'completed']);
                        $updatedCount++;

                        \Log::info('Bulk shipment status updated to completed', [
                            'shipment_id' => $bulkShipment->id,
                            'shipment_code' => $bulkShipment->shipment_id,
                            'old_status' => $oldStatus,
                            'new_status' => 'completed',
                            'reason' => 'Last package in bulk assignment completed',
                            'trigger_shipment_id' => $completedShipment->id,
                        ]);
                    }
                }

                \Log::info('Bulk assignment completion update finished', [
                    'bulk_assignment_id' => $bulkAssignment->id,
                    'updated_shipments_count' => $updatedCount,
                    'trigger_shipment_id' => $completedShipment->id,
                ]);
            } else {
                \Log::info('Not the last package - no bulk update needed', [
                    'completed_shipment_id' => $completedShipment->id,
                    'bulk_assignment_id' => $bulkAssignment->id,
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to update bulk assignment status on completion', [
                'shipment_id' => $completedShipment->id,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * ✅ NEW: Get bulk assignment route duration analysis (Admin version)
     * Admin bisa melihat laporan durasi rute untuk semua kurir
     */
    public function getAdminBulkAssignmentRouteDuration(Request $request, $bulkAssignmentId): JsonResponse
    {
        $user = $request->user();
        
        // Only admin can access this endpoint
        if (!$user->hasRole('Admin')) {
            return response()->json([
                'message' => 'This endpoint is only for admin'
            ], 403);
        }

        // Get bulk assignment (admin can see all)
        $bulkAssignment = DB::table('bulk_assignments as ba')
            ->join('users as admin', 'ba.admin_id', '=', 'admin.id')
            ->join('users as driver', 'ba.driver_id', '=', 'driver.id')
            ->join('vehicle_types as vt', 'ba.vehicle_type_id', '=', 'vt.id')
            ->where('ba.id', $bulkAssignmentId)
            ->select([
                'ba.*',
                'admin.name as admin_name',
                'driver.name as driver_name',
                'driver.phone as driver_phone',
                'vt.name as vehicle_type_name'
            ])
            ->first();

        if (!$bulkAssignment) {
            return response()->json([
                'message' => 'Bulk assignment not found'
            ], 404);
        }

        $shipmentIds = json_decode($bulkAssignment->shipment_ids, true);
        
        // Get shipments with destinations and status histories in the correct order
        $orderedShipments = collect();
        foreach ($shipmentIds as $shipmentId) {
            $shipment = Shipment::with([
                'destinations.statusHistories',
                'creator:id,name',
                'category:id,name'
            ])
            ->where('id', $shipmentId)
            ->first();
            
            if ($shipment) {
                $orderedShipments->push($shipment);
            }
        }

        if ($orderedShipments->isEmpty()) {
            return response()->json([
                'message' => 'No shipments found in this bulk assignment'
            ], 404);
        }

        // Calculate route duration analysis
        $routeAnalysis = $this->calculateBulkRouteAnalysis($orderedShipments);

        return response()->json([
            'message' => 'Admin bulk assignment route duration retrieved successfully',
            'data' => [
                'bulk_assignment' => [
                    'id' => $bulkAssignment->id,
                    'assigned_by' => $bulkAssignment->admin_name,
                    'driver' => [
                        'name' => $bulkAssignment->driver_name,
                        'phone' => $bulkAssignment->driver_phone,
                    ],
                    'vehicle_type' => $bulkAssignment->vehicle_type_name,
                    'assigned_at' => $bulkAssignment->assigned_at,
                    'assigned_at_formatted' => \Carbon\Carbon::parse($bulkAssignment->assigned_at)->format('d M Y, H:i'),
                    'total_shipments' => $bulkAssignment->shipment_count,
                ],
                'route_analysis' => $routeAnalysis,
                'performance_metrics' => $this->calculatePerformanceMetrics($routeAnalysis),
            ],
        ]);
    }

    /**
     * ✅ NEW: Get bulk assignment route duration analysis (Driver version)
     * Menghitung durasi perjalanan dari paket A → B → C → kembali ke kantor
     */
    public function getBulkAssignmentRouteDuration(Request $request, $bulkAssignmentId): JsonResponse
    {
        $user = $request->user();
        
        // Only drivers can access this endpoint
        if (!$user->hasRole('Kurir')) {
            return response()->json([
                'message' => 'This endpoint is only for drivers'
            ], 403);
        }

        // Get bulk assignment and verify it belongs to this driver
        $bulkAssignment = DB::table('bulk_assignments as ba')
            ->join('users as admin', 'ba.admin_id', '=', 'admin.id')
            ->join('vehicle_types as vt', 'ba.vehicle_type_id', '=', 'vt.id')
            ->where('ba.id', $bulkAssignmentId)
            ->where('ba.driver_id', $user->id)
            ->select([
                'ba.*',
                'admin.name as admin_name',
                'vt.name as vehicle_type_name'
            ])
            ->first();

        if (!$bulkAssignment) {
            return response()->json([
                'message' => 'Bulk assignment not found or not assigned to you'
            ], 404);
        }

        $shipmentIds = json_decode($bulkAssignment->shipment_ids, true);
        
        // Get shipments with destinations and status histories in the correct order
        $orderedShipments = collect();
        foreach ($shipmentIds as $shipmentId) {
            $shipment = Shipment::with([
                'destinations.statusHistories',
                'creator:id,name',
                'category:id,name'
            ])
            ->where('id', $shipmentId)
            ->where('assigned_driver_id', $user->id)
            ->first();
            
            if ($shipment) {
                $orderedShipments->push($shipment);
            }
        }

        if ($orderedShipments->isEmpty()) {
            return response()->json([
                'message' => 'No shipments found in this bulk assignment'
            ], 404);
        }

        // Calculate route duration analysis
        $routeAnalysis = $this->calculateBulkRouteAnalysis($orderedShipments);

        return response()->json([
            'message' => 'Bulk assignment route duration retrieved successfully',
            'data' => [
                'bulk_assignment' => [
                    'id' => $bulkAssignment->id,
                    'assigned_by' => $bulkAssignment->admin_name,
                    'vehicle_type' => $bulkAssignment->vehicle_type_name,
                    'assigned_at' => $bulkAssignment->assigned_at,
                    'assigned_at_formatted' => \Carbon\Carbon::parse($bulkAssignment->assigned_at)->format('d M Y, H:i'),
                ],
                'route_analysis' => $routeAnalysis,
            ],
        ]);
    }

    /**
     * Calculate comprehensive route analysis for bulk assignment
     * Paket A → Paket B → Paket C → Kembali ke Kantor
     */
    private function calculateBulkRouteAnalysis($orderedShipments): array
    {
        $routeSegments = [];
        $totalDeliveryDuration = 0;
        $totalReturnDuration = 0;
        $overallStartTime = null;
        $overallEndTime = null;

        // Analyze each delivery segment (A → B → C)
        foreach ($orderedShipments as $index => $shipment) {
            $destination = $shipment->destinations->first(); // Assuming 1 destination per shipment
            
            if (!$destination) {
                continue;
            }

            $segmentAnalysis = $this->analyzeDeliverySegment($destination, $index + 1);
            
            if ($segmentAnalysis) {
                $routeSegments[] = $segmentAnalysis;
                
                // Track overall timing
                if (!$overallStartTime && isset($segmentAnalysis['start_time'])) {
                    $overallStartTime = $segmentAnalysis['start_time'];
                }
                
                if (isset($segmentAnalysis['delivery_duration_minutes'])) {
                    $totalDeliveryDuration += $segmentAnalysis['delivery_duration_minutes'];
                }
                
                if (isset($segmentAnalysis['delivered_at'])) {
                    $overallEndTime = $segmentAnalysis['delivered_at'];
                }
            }
        }

        // Analyze return journey (dari paket terakhir kembali ke kantor)
        $returnAnalysis = $this->analyzeReturnJourney($orderedShipments->last());
        
        if ($returnAnalysis && isset($returnAnalysis['return_duration_minutes'])) {
            $totalReturnDuration = $returnAnalysis['return_duration_minutes'];
            $overallEndTime = $returnAnalysis['finished_at'];
        }

        // Calculate total route duration
        $totalRouteDuration = null;
        if ($overallStartTime && $overallEndTime) {
            $startTime = \Carbon\Carbon::parse($overallStartTime);
            $endTime = \Carbon\Carbon::parse($overallEndTime);
            $duration = $endTime->diff($startTime);
            $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
            
            $totalRouteDuration = [
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 2),
                'human_readable' => $this->formatDuration($duration),
                'start_time' => $overallStartTime,
                'end_time' => $overallEndTime,
            ];
        }

        return [
            'delivery_segments' => $routeSegments,
            'return_journey' => $returnAnalysis,
            'summary' => [
                'total_packages' => $orderedShipments->count(),
                'total_delivery_duration' => [
                    'minutes' => $totalDeliveryDuration,
                    'hours' => round($totalDeliveryDuration / 60, 2),
                    'human_readable' => $this->minutesToHumanReadable($totalDeliveryDuration),
                ],
                'total_return_duration' => [
                    'minutes' => $totalReturnDuration,
                    'hours' => round($totalReturnDuration / 60, 2),
                    'human_readable' => $this->minutesToHumanReadable($totalReturnDuration),
                ],
                'total_route_duration' => $totalRouteDuration,
                'average_delivery_time_per_package' => $orderedShipments->count() > 0 ? 
                    round($totalDeliveryDuration / $orderedShipments->count(), 2) : 0,
            ],
        ];
    }

    /**
     * Analyze individual delivery segment (e.g., Paket A: mulai → sampai → diterima)
     */
    private function analyzeDeliverySegment($destination, int $packageNumber): ?array
    {
        $histories = $destination->statusHistories()
            ->orderBy('changed_at', 'asc')
            ->get();

        if ($histories->isEmpty()) {
            return null;
        }

        $statusTimes = [];
        foreach ($histories as $history) {
            $statusTimes[$history->new_status] = $history->changed_at;
        }

        $segmentData = [
            'package_number' => $packageNumber,
            'package_label' => "Paket " . $this->numberToLetter($packageNumber),
            'destination_address' => $destination->delivery_address,
            'receiver_name' => $destination->receiver_name,
            'current_status' => $destination->status,
            'status_label' => $this->getStatusLabel($destination->status),
        ];

        // Calculate delivery duration (mulai pengiriman → diterima)
        $startStatus = $statusTimes['in_progress'] ?? $statusTimes['picked'] ?? $statusTimes['pending'] ?? null;
        $deliveredTime = $statusTimes['delivered'] ?? null;

        if ($startStatus && $deliveredTime) {
            $duration = $deliveredTime->diff($startStatus);
            $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
            
            $segmentData['delivery_duration'] = [
                'start_time' => $startStatus->format('Y-m-d H:i:s'),
                'delivered_at' => $deliveredTime->format('Y-m-d H:i:s'),
                'duration_minutes' => $totalMinutes,
                'duration_hours' => round($totalMinutes / 60, 2),
                'human_readable' => $this->formatDuration($duration),
            ];
            
            $segmentData['delivery_duration_minutes'] = $totalMinutes;
            $segmentData['start_time'] = $startStatus->format('Y-m-d H:i:s');
            $segmentData['delivered_at'] = $deliveredTime->format('Y-m-d H:i:s');
        }

        // Add detailed status timeline
        $timeline = [];
        $statusFlow = ['pending', 'picked', 'in_progress', 'arrived', 'delivered'];
        
        for ($i = 0; $i < count($statusFlow) - 1; $i++) {
            $fromStatus = $statusFlow[$i];
            $toStatus = $statusFlow[$i + 1];
            
            if (isset($statusTimes[$fromStatus]) && isset($statusTimes[$toStatus])) {
                $stepDuration = $statusTimes[$toStatus]->diff($statusTimes[$fromStatus]);
                $stepMinutes = ($stepDuration->days * 24 * 60) + ($stepDuration->h * 60) + $stepDuration->i;
                
                $timeline[] = [
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'from_label' => $this->getStatusLabel($fromStatus),
                    'to_label' => $this->getStatusLabel($toStatus),
                    'duration_minutes' => $stepMinutes,
                    'human_readable' => $this->formatDuration($stepDuration),
                    'start_time' => $statusTimes[$fromStatus]->format('Y-m-d H:i:s'),
                    'end_time' => $statusTimes[$toStatus]->format('Y-m-d H:i:s'),
                ];
            }
        }
        
        $segmentData['detailed_timeline'] = $timeline;

        return $segmentData;
    }

    /**
     * Analyze return journey (dari paket terakhir kembali ke kantor)
     */
    private function analyzeReturnJourney($lastShipment): ?array
    {
        if (!$lastShipment) {
            return null;
        }

        $lastDestination = $lastShipment->destinations->first();
        if (!$lastDestination) {
            return null;
        }

        $histories = $lastDestination->statusHistories()
            ->orderBy('changed_at', 'asc')
            ->get();

        $statusTimes = [];
        foreach ($histories as $history) {
            $statusTimes[$history->new_status] = $history->changed_at;
        }

        $returningTime = $statusTimes['returning'] ?? null;
        $finishedTime = $statusTimes['finished'] ?? null;

        if (!$returningTime || !$finishedTime) {
            return [
                'status' => 'incomplete',
                'message' => 'Return journey not completed yet',
                'returning_time' => $returningTime ? $returningTime->format('Y-m-d H:i:s') : null,
                'finished_time' => $finishedTime ? $finishedTime->format('Y-m-d H:i:s') : null,
            ];
        }

        $duration = $finishedTime->diff($returningTime);
        $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;

        return [
            'status' => 'completed',
            'return_duration' => [
                'returning_at' => $returningTime->format('Y-m-d H:i:s'),
                'finished_at' => $finishedTime->format('Y-m-d H:i:s'),
                'duration_minutes' => $totalMinutes,
                'duration_hours' => round($totalMinutes / 60, 2),
                'human_readable' => $this->formatDuration($duration),
            ],
            'return_duration_minutes' => $totalMinutes,
            'finished_at' => $finishedTime->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert number to letter (1=A, 2=B, 3=C, etc.)
     */
    private function numberToLetter(int $number): string
    {
        return chr(64 + $number); // 65 = A, 66 = B, etc.
    }

    /**
     * Convert minutes to human readable format
     */
    private function minutesToHumanReadable(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' menit';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes === 0) {
            return $hours . ' jam';
        }
        
        return $hours . ' jam ' . $remainingMinutes . ' menit';
    }

    /**
     * ✅ NEW: Calculate performance metrics for admin reports
     */
    private function calculatePerformanceMetrics(array $routeAnalysis): array
    {
        $summary = $routeAnalysis['summary'] ?? [];
        $deliverySegments = $routeAnalysis['delivery_segments'] ?? [];
        $returnJourney = $routeAnalysis['return_journey'] ?? [];

        $metrics = [
            'efficiency_score' => 0,
            'speed_rating' => 'Unknown',
            'completion_status' => 'Incomplete',
            'recommendations' => [],
        ];

        // Calculate efficiency score (0-100)
        $totalPackages = $summary['total_packages'] ?? 0;
        $avgDeliveryTime = $summary['average_delivery_time_per_package'] ?? 0;
        
        if ($totalPackages > 0 && $avgDeliveryTime > 0) {
            // Base efficiency on average delivery time (lower is better)
            // Assume 30 minutes per package is optimal
            $optimalTimePerPackage = 30; // minutes
            $efficiencyRatio = $optimalTimePerPackage / max($avgDeliveryTime, 1);
            $metrics['efficiency_score'] = min(100, round($efficiencyRatio * 100, 1));
        }

        // Speed rating based on average delivery time
        if ($avgDeliveryTime > 0) {
            if ($avgDeliveryTime <= 20) {
                $metrics['speed_rating'] = 'Sangat Cepat';
            } elseif ($avgDeliveryTime <= 30) {
                $metrics['speed_rating'] = 'Cepat';
            } elseif ($avgDeliveryTime <= 45) {
                $metrics['speed_rating'] = 'Normal';
            } elseif ($avgDeliveryTime <= 60) {
                $metrics['speed_rating'] = 'Lambat';
            } else {
                $metrics['speed_rating'] = 'Sangat Lambat';
            }
        }

        // Completion status
        $completedSegments = 0;
        foreach ($deliverySegments as $segment) {
            if (isset($segment['delivery_duration'])) {
                $completedSegments++;
            }
        }

        if ($completedSegments === $totalPackages && isset($returnJourney['status']) && $returnJourney['status'] === 'completed') {
            $metrics['completion_status'] = 'Selesai Semua';
        } elseif ($completedSegments === $totalPackages) {
            $metrics['completion_status'] = 'Pengiriman Selesai, Belum Kembali';
        } elseif ($completedSegments > 0) {
            $metrics['completion_status'] = "Sebagian Selesai ({$completedSegments}/{$totalPackages})";
        } else {
            $metrics['completion_status'] = 'Belum Dimulai';
        }

        // Generate recommendations
        $recommendations = [];
        
        if ($avgDeliveryTime > 45) {
            $recommendations[] = 'Waktu pengiriman per paket terlalu lama, pertimbangkan optimasi rute';
        }
        
        if ($metrics['efficiency_score'] < 70) {
            $recommendations[] = 'Efisiensi pengiriman perlu ditingkatkan';
        }
        
        if (isset($returnJourney['return_duration']['duration_minutes']) && $returnJourney['return_duration']['duration_minutes'] > 60) {
            $recommendations[] = 'Waktu perjalanan pulang terlalu lama';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Performa pengiriman sudah baik';
        }
        
        $metrics['recommendations'] = $recommendations;

        // Additional detailed metrics
        $metrics['detailed_analysis'] = [
            'total_route_time' => $summary['total_route_duration']['human_readable'] ?? 'Belum selesai',
            'delivery_efficiency' => [
                'average_per_package' => round($avgDeliveryTime, 1) . ' menit',
                'fastest_delivery' => $this->getFastestDelivery($deliverySegments),
                'slowest_delivery' => $this->getSlowestDelivery($deliverySegments),
            ],
            'return_efficiency' => [
                'return_time' => isset($returnJourney['return_duration']) ? 
                    $returnJourney['return_duration']['human_readable'] : 'Belum kembali',
                'return_speed_rating' => $this->getReturnSpeedRating($returnJourney),
            ],
        ];

        return $metrics;
    }

    /**
     * Get fastest delivery from segments
     */
    private function getFastestDelivery(array $segments): string
    {
        $fastestTime = PHP_INT_MAX;
        $fastestPackage = null;
        
        foreach ($segments as $segment) {
            if (isset($segment['delivery_duration']['duration_minutes'])) {
                $time = $segment['delivery_duration']['duration_minutes'];
                if ($time < $fastestTime) {
                    $fastestTime = $time;
                    $fastestPackage = $segment['package_label'];
                }
            }
        }
        
        return $fastestPackage ? "{$fastestPackage}: {$fastestTime} menit" : 'Belum ada data';
    }

    /**
     * Get slowest delivery from segments
     */
    private function getSlowestDelivery(array $segments): string
    {
        $slowestTime = 0;
        $slowestPackage = null;
        
        foreach ($segments as $segment) {
            if (isset($segment['delivery_duration']['duration_minutes'])) {
                $time = $segment['delivery_duration']['duration_minutes'];
                if ($time > $slowestTime) {
                    $slowestTime = $time;
                    $slowestPackage = $segment['package_label'];
                }
            }
        }
        
        return $slowestPackage ? "{$slowestPackage}: {$slowestTime} menit" : 'Belum ada data';
    }

    /**
     * Get return speed rating
     */
    private function getReturnSpeedRating(array $returnJourney): string
    {
        if (!isset($returnJourney['return_duration']['duration_minutes'])) {
            return 'Belum kembali';
        }
        
        $returnTime = $returnJourney['return_duration']['duration_minutes'];
        
        if ($returnTime <= 15) {
            return 'Sangat Cepat';
        } elseif ($returnTime <= 30) {
            return 'Cepat';
        } elseif ($returnTime <= 45) {
            return 'Normal';
        } elseif ($returnTime <= 60) {
            return 'Lambat';
        } else {
            return 'Sangat Lambat';
        }
    }

    /**
     * Check if this is the last package in bulk assignment based on multi-package rules
     */
    private function isLastPackageInBulkAssignment(Shipment $completedShipment, $allBulkShipments, array $shipmentIds): bool
    {
        // Get the position of completed shipment in the original admin-selected order
        $completedShipmentIndex = array_search($completedShipment->id, $shipmentIds);
        
        if ($completedShipmentIndex === false) {
            return false;
        }

        // According to multi-package rules: only the last package can go to finished
        // So if this shipment reached finished, it must be the last package
        $isLastInOrder = $completedShipmentIndex === (count($shipmentIds) - 1);
        
        \Log::info('Checking if last package in bulk assignment', [
            'completed_shipment_id' => $completedShipment->id,
            'completed_shipment_index' => $completedShipmentIndex,
            'total_shipments' => count($shipmentIds),
            'is_last_in_order' => $isLastInOrder,
            'shipment_order' => $shipmentIds,
        ]);

        return $isLastInOrder;
    }
}

// namespace App\Http\Controllers\Api;

// use App\Http\Controllers\Controller;
// use App\Models\Shipment;
// use App\Models\ShipmentDestination;
// use App\Models\ShipmentProgress;
// use App\Services\NotificationService;
// use Illuminate\Http\JsonResponse;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Storage;
// use Intervention\Image\ImageManager;
// use Intervention\Image\Drivers\Gd\Driver;

// class ShipmentProgressController extends Controller
// {
//     public function updateProgress(Request $request, Shipment $shipment, ShipmentDestination $destination): JsonResponse
//     {
//         if ($shipment->assigned_driver_id !== auth()->id()) {
//             return response()->json([
//                 'message' => 'You are not assigned to this shipment'
//             ], 403);
//         }

//         $request->validate([
//             'status' => 'required|in:arrived,delivered,failed',
//             'photo' => 'required|image|max:4096', // 4MB max
//             'note' => 'nullable|string',
//             'receiver_name' => 'required_if:status,delivered|string',
//             'received_photo' => 'nullable|image|max:4096',
//         ]);

//         try {
//             // Handle photo upload
//             $photoPath = null;
//             $thumbnailPath = null;

//             if ($request->hasFile('photo')) {
//                 $photo = $request->file('photo');
//                 $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();

//                 // Store original photo
//                 $photoPath = $photo->storeAs('shipment-photos', $filename, 'public');

//                 // Create thumbnail
//                 $thumbnailFilename = 'thumb_' . $filename;
//                 $manager = new ImageManager(new Driver());
//                 $image = $manager->read($photo);
//                 $image->resize(300, 300);

//                 $thumbnailPath = 'shipment-photos/' . $thumbnailFilename;
//                 Storage::disk('public')->put($thumbnailPath, $image->encode());
//             }

//             // Handle received photo
//             $receivedPhotoPath = null;
//             if ($request->hasFile('received_photo')) {
//                 $receivedPhoto = $request->file('received_photo');
//                 $receivedFilename = 'received_' . time() . '_' . uniqid() . '.' . $receivedPhoto->getClientOriginalExtension();
//                 $receivedPhotoPath = $receivedPhoto->storeAs('shipment-photos', $receivedFilename, 'public');
//             }

//             // Create progress record
//             $progress = ShipmentProgress::create([
//                 'shipment_id' => $shipment->id,
//                 'destination_id' => $destination->id,
//                 'driver_id' => auth()->id(),
//                 'status' => $request->status,
//                 'progress_time' => now(),
//                 'photo_url' => $photoPath,
//                 'photo_thumbnail' => $thumbnailPath,
//                 'note' => $request->note,
//                 'receiver_name' => $request->receiver_name,
//                 'received_photo_url' => $receivedPhotoPath,
//             ]);

//             // Update destination status
//             // $destination->update(['status' => $request->status]);
//             $statusMap = [
//                 'arrived' => 'in_progress',
//                 'delivered' => 'completed',
//                 'failed' => 'failed',
//             ];

//             $destination->update(['status' => $statusMap[$request->status] ?? 'pending']);

//             // Send notification for destination delivery
//             if ($request->status === 'delivered') {
//                 app(NotificationService::class)->destinationDelivered(
//                     $shipment->load(['creator', 'driver']),
//                     $destination,
//                     $progress
//                 );
//             }

//             // Check if all destinations are completed
//             $allDestinationsCompleted = $shipment->destinations()
//                 ->where('status', '!=', 'completed')
//                 ->count() === 0;

//             if ($allDestinationsCompleted) {
//                 $shipment->update(['status' => 'completed']);

//                 // Send completion notification
//                 app(NotificationService::class)->deliveryCompleted($shipment->load(['creator', 'driver']));
//             }

//             return response()->json([
//                 'message' => 'Progress updated successfully',
//                 'data' => $progress->load(['destination', 'driver'])
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'message' => 'Failed to update progress',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }

//     public function getProgress(Shipment $shipment): JsonResponse
//     {
//         $progress = $shipment->progress()
//             ->with(['destination', 'driver'])
//             ->orderBy('progress_time', 'desc')
//             ->get();

//         return response()->json([
//             'data' => $progress
//         ]);
//     }

//     public function getDriverHistory(Request $request): JsonResponse
//     {
//         $driverId = $request->user()->id;

//         $history = ShipmentProgress::with(['shipment', 'destination'])
//             ->where('driver_id', $driverId)
//             ->orderBy('progress_time', 'desc')
//             ->paginate(20);

//         return response()->json([
//             'data' => $history
//         ]);
//     }
// }
