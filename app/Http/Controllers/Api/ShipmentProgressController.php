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
    public function updateProgress(Request $request, Shipment $shipment, ShipmentDestination $destination): JsonResponse
    {
        // Log request untuk debug
        \Log::info('Update Progress Request', [
            'shipment_id' => $shipment->id,
            'destination_id' => $destination->id,
            'status' => $request->status,
            'has_photo' => $request->hasFile('photo'),
            'user_id' => auth()->id(),
        ]);

        if ($shipment->assigned_driver_id !== auth()->id()) {
            return response()->json([
                'message' => 'You are not assigned to this shipment',
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:picked,in_progress,arrived,delivered,completed,returning,finished,takeover,failed',
            'photo' => 'nullable|image|max:4096', // 4MB max
            'note' => 'nullable|string',
            'receiver_name' => 'required_if:status,delivered|string',
            'received_photo' => 'nullable|image|max:4096',
            'takeover_reason' => 'required_if:status,takeover|string',
        ]);

        // Validasi: Status flow yang benar - tidak boleh lompat
        $validStatusTransitions = [
            'pending' => ['picked'],
            'picked' => ['in_progress', 'takeover'],
            'in_progress' => ['arrived', 'takeover', 'failed'],
            'arrived' => ['delivered', 'failed'],
            'delivered' => ['completed'],
            'completed' => ['returning'],
            'returning' => ['finished'],
            'finished' => [], // Status final
            'takeover' => [], // Status final (kembali ke admin)
            'failed' => [], // Status final
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

        // Validasi: Pickup bisa dilakukan saat shipment status = assigned atau in_progress
        if ($request->status === 'picked' && ! in_array($shipment->status, ['assigned', 'in_progress'])) {
            return response()->json([
                'message' => 'Pickup can only be done when shipment is assigned or in progress',
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
                    $filename = time().'_'.uniqid().'.'.$photo->getClientOriginalExtension();

                    // Simpan original photo
                    $photoPath = $photo->storeAs('shipment-photos', $filename, 'public');

                    // Buat thumbnail
                    $thumbnailFilename = 'thumb_'.$filename;
                    $manager = new ImageManager(new Driver);
                    $image = $manager->read($photo)->resize(300, 300);

                    $thumbnailPath = 'shipment-photos/'.$thumbnailFilename;
                    Storage::disk('public')->put($thumbnailPath, $image->encode());
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
                $receivedPhoto = $request->file('received_photo');
                $receivedFilename = 'received_'.time().'_'.uniqid().'.'.$receivedPhoto->getClientOriginalExtension();
                $receivedPhotoPath = $receivedPhoto->storeAs('shipment-photos', $receivedFilename, 'public');
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

            // === Handle status COMPLETED: cek apakah semua destination completed ===
            if ($request->status === 'completed') {
                // Refresh shipment untuk get latest destination status
                $shipment->refresh();

                // Cek apakah semua destination sudah completed
                $totalDestinations = $shipment->destinations()->count();
                $completedDestinations = $shipment->destinations()
                    ->where('status', 'completed')
                    ->count();

                \Log::info('Checking all destinations completed', [
                    'total' => $totalDestinations,
                    'completed' => $completedDestinations,
                ]);

                if ($completedDestinations === $totalDestinations) {
                    // Auto-set semua destination ke returning
                    $shipment->destinations()
                        ->where('status', 'completed')
                        ->update(['status' => 'returning']);

                    \Log::info('All destinations completed, auto-set to returning');

                    try {
                        app(NotificationService::class)->deliveryCompleted(
                            $shipment->load(['creator', 'driver'])
                        );
                    } catch (\Exception $e) {
                        \Log::warning('Completion notification failed', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // === Handle status TAKEOVER: kembalikan ke admin ===
            if ($request->status === 'takeover') {
                $shipment->update([
                    'status' => 'pending',
                    'assigned_driver_id' => null,
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
                $allDestinationsFinished = $shipment->destinations()
                    ->where('status', 'finished')
                    ->count() === $shipment->destinations()->count();

                if ($allDestinationsFinished) {
                    $shipment->update(['status' => 'completed']);
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

    public function getProgress(Shipment $shipment): JsonResponse
    {
        $progress = $shipment->progress()
            ->with(['destination', 'driver'])
            ->orderBy('progress_time', 'desc')
            ->get();

        return response()->json([
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

        // Status flow yang benar berurutan
        $statusFlow = ['pending', 'picked', 'in_progress', 'arrived', 'delivered', 'completed', 'returning', 'finished'];
        
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
            'picked_to_in_progress' => 'Proses pengiriman',
            'in_progress_to_arrived' => 'Sampai di lokasi',
            'arrived_to_delivered' => 'Sudah diterima',
            'delivered_to_completed' => 'Barang completed semua',
            'completed_to_returning' => 'Arah pulang ke kantor',
            'returning_to_finished' => 'Sudah sampai di kantor finish',
            'picked_to_takeover' => 'Driver melakukan takeover setelah pickup',
            'in_progress_to_takeover' => 'Driver melakukan takeover saat dalam perjalanan',
            'in_progress_to_failed' => 'Pengiriman gagal',
            'arrived_to_failed' => 'Gagal menyerahkan barang',
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
        $statusFlow = ['pending', 'picked', 'in_progress', 'arrived', 'delivered', 'completed', 'returning', 'finished'];

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
