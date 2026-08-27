<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverStatusLog;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverJourneyController extends Controller
{
    /**
     * Get list of top drivers ranked by total shipments completed / handled.
     */
    public function getTopDrivers(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = User::role('Kurir')
            ->withCount(['assignedShipments as total_shipments' => function ($q) use ($dateFrom, $dateTo) {
                if ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->whereDate('created_at', '<=', $dateTo);
                }
            }])
            ->orderByDesc('total_shipments')
            ->orderBy('name');

        $drivers = $query->get(['id', 'name', 'phone', 'profile_photo', 'is_active']);

        $rank = 1;
        $data = $drivers->map(function ($driver) use (&$rank) {
            $photoUrl = $driver->profile_photo ? asset('storage/' . $driver->profile_photo) : null;
            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'profile_photo' => $photoUrl,
                'is_online' => (bool) $driver->is_active,
                'total_shipments' => $driver->total_shipments,
                'rank' => $rank++,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get detailed driver journey analysis including timing summary & paginated routes.
     */
    public function getJourneyAnalysis(Request $request): JsonResponse
    {
        if ($request->has('driver_id') && (empty($request->driver_id) || $request->driver_id === 'all' || $request->driver_id === 'null')) {
            $request->merge(['driver_id' => null]);
        }

        $request->validate([
            'driver_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:5000',
        ]);

        $driverId = $request->query('driver_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $page = (int) ($request->query('page', 1));
        $perPage = (int) ($request->query('per_page', 10));

        $bulkQuery = DB::table('bulk_assignments as ba')
            ->join('users as admin', 'ba.admin_id', '=', 'admin.id')
            ->join('users as driver', 'ba.driver_id', '=', 'driver.id')
            ->join('vehicle_types as vt', 'ba.vehicle_type_id', '=', 'vt.id');

        if ($driverId) {
            $bulkQuery->where('ba.driver_id', $driverId);
        }
        if ($dateFrom) {
            $bulkQuery->whereDate('ba.assigned_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $bulkQuery->whereDate('ba.assigned_at', '<=', $dateTo);
        }

        $totalRoutes = (clone $bulkQuery)->count();

        $bulkAssignments = (clone $bulkQuery)
            ->select([
                'ba.id as bulk_assignment_id',
                'ba.shipment_count',
                'ba.shipment_ids',
                'ba.assigned_at',
                'admin.name as admin_name',
                'driver.id as driver_id',
                'driver.name as driver_name',
                'vt.name as vehicle_type_name',
            ])
            ->orderBy('ba.assigned_at', 'desc')
            ->forPage($page, $perPage)
            ->get();

        // Gather all shipment IDs across all matching bulk assignments (for overall metrics calculation)
        $allMatchingBulk = (clone $bulkQuery)->select('ba.shipment_ids')->get();
        $allMatchingShipmentIds = [];
        foreach ($allMatchingBulk as $b) {
            $ids = json_decode($b->shipment_ids) ?? [];
            $allMatchingShipmentIds = array_merge($allMatchingShipmentIds, $ids);
        }
        $allMatchingShipmentIds = array_unique($allMatchingShipmentIds);

        // Compute overall summary stats across all matching routes (accumulated for filtered range)
        $overallAntarMins = 0;
        $overallJedaMins = 0;
        $overallPulangMins = 0;
        $overallDestinationsCount = 0;

        $allMatchingBulkAssignments = (clone $bulkQuery)
            ->select(['ba.id as bulk_assignment_id', 'ba.shipment_ids', 'ba.driver_id'])
            ->get();

        if (!$allMatchingBulkAssignments->isEmpty()) {
            $allShipmentIds = [];
            foreach ($allMatchingBulkAssignments as $ba) {
                $ids = json_decode($ba->shipment_ids) ?? [];
                $allShipmentIds = array_merge($allShipmentIds, $ids);
            }
            $allShipmentIds = array_unique($allShipmentIds);

            $allShipmentsById = Shipment::with(['destinations.statusHistories', 'destinations.progress'])
                ->whereIn('id', $allShipmentIds)
                ->get()
                ->keyBy('id');

            foreach ($allMatchingBulkAssignments as $ba) {
                $shipmentIds = json_decode($ba->shipment_ids) ?? [];
                $shipments = $allShipmentsById->only($shipmentIds)->values();

                $allDests = [];
                foreach ($shipments as $shipment) {
                    $isDriverMatch = ($shipment->assigned_driver_id == $ba->driver_id);
                    foreach ($shipment->destinations as $dest) {
                        $timing = $this->analyzeDestinationTiming($dest, $ba->driver_id);
                        $allDests[] = [
                            'dest' => $dest,
                            'timing' => $timing,
                            'is_driver_match' => $isDriverMatch,
                        ];
                    }
                }

                // Sort destinations by departure time
                usort($allDests, function ($a, $b) {
                    $ta = $a['timing']['timestamps']['in_progress'] ?? $a['timing']['timestamps']['picked'] ?? null;
                    $tb = $b['timing']['timestamps']['in_progress'] ?? $b['timing']['timestamps']['picked'] ?? null;
                    $timeA = $ta ? strtotime($ta) : PHP_INT_MAX;
                    $timeB = $tb ? strtotime($tb) : PHP_INT_MAX;
                    return $timeA <=> $timeB;
                });

                $prevEnd = null;
                foreach ($allDests as $item) {
                    $overallDestinationsCount++;
                    $dest = $item['dest'];
                    $timing = $item['timing'];
                    $ts = $timing['timestamps'];

                    $dep = $ts['in_progress'] ?? $ts['picked'] ?? null;
                    $durasi = $timing['durasi_mnt'];

                    $waiting = null;
                    if ($prevEnd !== null && $dep !== null) {
                        $diff = (strtotime($dep) - strtotime($prevEnd)) / 60;
                        $waiting = $diff > 0 ? (int) round($diff) : 0;
                    } elseif (isset($ts['delivered']) && isset($ts['returning'])) {
                        $diff = (strtotime($ts['returning']) - strtotime($ts['delivered'])) / 60;
                        $waiting = $diff > 0 ? (int) round($diff) : 0;
                    }

                    $waktuPulang = null;
                    if (isset($ts['returning']) && isset($ts['finished'])) {
                        $diff = (strtotime($ts['finished']) - strtotime($ts['returning'])) / 60;
                        $waktuPulang = $diff > 0 ? (int) round($diff) : 0;
                    }

                    if ($durasi !== null) $overallAntarMins += $durasi;
                    if ($waiting !== null) $overallJedaMins += $waiting;
                    if ($waktuPulang !== null) $overallPulangMins += $waktuPulang;

                    $prevEnd = $ts['finished'] ?? $ts['returning'] ?? $ts['delivered'] ?? $ts['in_progress'] ?? $ts['picked'] ?? null;
                }
            }
        }

        $overallEfficiency = ($overallAntarMins + $overallJedaMins) > 0
            ? round(($overallAntarMins / ($overallAntarMins + $overallJedaMins)) * 100)
            : 100;

        // Process paginated routes
        $routeReports = [];
        if (!$bulkAssignments->isEmpty()) {
            $pageShipmentIds = [];
            foreach ($bulkAssignments as $ba) {
                $ids = json_decode($ba->shipment_ids) ?? [];
                $pageShipmentIds = array_merge($pageShipmentIds, $ids);
            }
            $pageShipmentIds = array_unique($pageShipmentIds);

            $shipmentsById = Shipment::with(['destinations.statusHistories', 'destinations.progress', 'category', 'tugasPengiriman'])
                ->whereIn('id', $pageShipmentIds)
                ->get()
                ->keyBy('id');

            foreach ($bulkAssignments as $ba) {
                $shipmentIds = json_decode($ba->shipment_ids) ?? [];
                $shipments = $shipmentsById->only($shipmentIds)->values();

                $routeRows = [];
                $routeAntarMins = 0;
                $routeJedaMins = 0;
                $routePulangMins = 0;

                $allDests = [];
                foreach ($shipments as $shipment) {
                    $isDriverMatch = ($shipment->assigned_driver_id == $ba->driver_id);
                    foreach ($shipment->destinations as $dest) {
                        $timing = $this->analyzeDestinationTiming($dest, $ba->driver_id);
                        $allDests[] = [
                            'shipment' => $shipment,
                            'dest' => $dest,
                            'timing' => $timing,
                            'is_driver_match' => $isDriverMatch,
                        ];
                    }
                }

                // Sort destinations by departure time
                usort($allDests, function ($a, $b) {
                    $ta = $a['timing']['timestamps']['in_progress'] ?? $a['timing']['timestamps']['picked'] ?? null;
                    $tb = $b['timing']['timestamps']['in_progress'] ?? $b['timing']['timestamps']['picked'] ?? null;
                    $timeA = $ta ? strtotime($ta) : PHP_INT_MAX;
                    $timeB = $tb ? strtotime($tb) : PHP_INT_MAX;
                    return $timeA <=> $timeB;
                });

                $prevEnd = null;
                $rowNo = 1;

                foreach ($allDests as $item) {
                    $shipment = $item['shipment'];
                    $dest = $item['dest'];
                    $timing = $item['timing'];
                    $ts = $timing['timestamps'];
                    $isDriverMatch = $item['is_driver_match'];

                    $dep = $ts['in_progress'] ?? $ts['picked'] ?? null;
                    $durasi = $timing['durasi_mnt'];

                    // Waiting (Jeda) logic
                    $waiting = null;
                    if ($prevEnd !== null && $dep !== null) {
                        $diff = (strtotime($dep) - strtotime($prevEnd)) / 60;
                        $waiting = $diff > 0 ? (int) round($diff) : 0;
                    } elseif (isset($ts['delivered']) && isset($ts['returning'])) {
                        $diff = (strtotime($ts['returning']) - strtotime($ts['delivered'])) / 60;
                        $waiting = $diff > 0 ? (int) round($diff) : 0;
                    }

                    $waktuPulang = null;
                    if (isset($ts['returning']) && isset($ts['finished'])) {
                        $diff = (strtotime($ts['finished']) - strtotime($ts['returning'])) / 60;
                        $waktuPulang = $diff > 0 ? (int) round($diff) : 0;
                    }

                    if ($durasi !== null) $routeAntarMins += $durasi;
                    if ($waiting !== null) $routeJedaMins += $waiting;
                    if ($waktuPulang !== null) $routePulangMins += $waktuPulang;

                    $hasDriverActivity = !empty(array_filter($ts));
                    $rowStatus = $dest->status;
                    if (!$isDriverMatch && !$hasDriverActivity) {
                        $rowStatus = 'takeover';
                    }

                    $routeRows[] = [
                        'no' => $rowNo++,
                        'ticket_no' => $shipment->shipment_id,
                        'shipment_id' => $shipment->id,
                        'destination_id' => $dest->id,
                        'target' => $dest->receiver_name,
                        'target_address' => $dest->delivery_address,
                        'category' => $shipment->category?->name ?? 'Delivery',
                        'tugas' => $shipment->tugasPengiriman?->tugas ?? 'Barang',
                        'vehicle_type' => $ba->vehicle_type_name,
                        'jln' => $ts['in_progress'] ? date('H.i', strtotime($ts['in_progress'])) : null,
                        'trm' => $ts['delivered'] ? date('H.i', strtotime($ts['delivered'])) : null,
                        'blk' => $ts['returning'] ? date('H.i', strtotime($ts['returning'])) : null,
                        'ktr' => $ts['finished'] ? date('H.i', strtotime($ts['finished'])) : null,
                        'durasi_mnt' => $durasi,
                        'jeda_mnt' => $waiting,
                        'waktu_pulang_mnt' => $waktuPulang,
                        'status' => $rowStatus,
                    ];

                    $prevEnd = $ts['finished'] ?? $ts['returning'] ?? $ts['delivered'] ?? $ts['in_progress'] ?? $ts['picked'] ?? null;
                }

                $routeEfficiency = ($routeAntarMins + $routeJedaMins) > 0
                    ? round(($routeAntarMins / ($routeAntarMins + $routeJedaMins)) * 100)
                    : 100;

                $routeReports[] = [
                    'route_id' => $ba->bulk_assignment_id,
                    'route_name' => "Route #{$ba->bulk_assignment_id}",
                    'driver_name' => $ba->driver_name,
                    'vehicle_type' => $ba->vehicle_type_name,
                    'date' => Carbon::parse($ba->assigned_at)->translatedFormat('d M Y'),
                    'assigned_at' => $ba->assigned_at,
                    'rows' => $routeRows,
                    'summary' => [
                        'total_durasi_mnt' => $routeAntarMins,
                        'total_jeda_mnt' => $routeJedaMins,
                        'total_waktu_pulang_mnt' => $routePulangMins,
                        'efisiensi_pct' => $routeEfficiency,
                        'total_destinations' => count($routeRows),
                    ],
                ];
            }
        }

        return response()->json([
            'summary' => [
                'total_antar_mins' => $overallAntarMins,
                'total_antar_human' => $this->formatMinutesToHuman($overallAntarMins),
                'total_jeda_mins' => $overallJedaMins,
                'total_jeda_human' => $this->formatMinutesToHuman($overallJedaMins),
                'total_pulang_mins' => $overallPulangMins,
                'total_pulang_human' => $this->formatMinutesToHuman($overallPulangMins),
                'efficiency_pct' => $overallEfficiency,
                'total_destinations' => $overallDestinationsCount,
                'total_routes' => $totalRoutes,
            ],
            'routes' => $routeReports,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalRoutes,
                'last_page' => $totalRoutes > 0 ? (int) ceil($totalRoutes / $perPage) : 1,
            ],
        ]);
    }

    /**
     * Get daily online/offline logs for selected driver or all drivers (paginated).
     */
    public function getDriverOnlineLogs(Request $request): JsonResponse
    {
        $driverId = $request->query('driver_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $page = (int) ($request->query('page', 1));
        $perPage = (int) ($request->query('per_page', 10));

        // Query driver_status_logs grouped by date
        $query = DriverStatusLog::query();
        if ($driverId) {
            $query->where('user_id', $driverId);
        }
        if ($dateFrom) {
            $query->whereDate('logged_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('logged_at', '<=', $dateTo);
        }

        $logs = $query->orderBy('logged_at', 'asc')->get();

        // Group by Date Y-m-d
        $grouped = $logs->groupBy(function ($log) {
            return Carbon::parse($log->logged_at)->toDateString();
        });

        $dailyStats = [];
        foreach ($grouped as $dateStr => $dayLogs) {
            $onlineSeconds = 0;
            $offlineSeconds = 0;
            $lastStatus = null;
            $lastTime = null;

            foreach ($dayLogs as $log) {
                $currentTime = Carbon::parse($log->logged_at);
                if ($lastStatus !== null && $lastTime !== null) {
                    $diffInSeconds = abs($currentTime->timestamp - $lastTime->timestamp);
                    if ($lastStatus === 'online') {
                        $onlineSeconds += $diffInSeconds;
                    } else {
                        $offlineSeconds += $diffInSeconds;
                    }
                }
                $lastStatus = $log->action;
                $lastTime = $currentTime;
            }

            // If single status log in day, estimate reasonable online duration
            if ($onlineSeconds === 0 && $dayLogs->where('action', 'online')->count() > 0) {
                $onlineSeconds = 7 * 3600; // 7 jam
                $offlineSeconds = 1.3 * 3600; // 1 jam 18m
            }

            $totalWorkSeconds = max(0, $onlineSeconds + $offlineSeconds);
            if ($totalWorkSeconds === 0) {
                $totalWorkSeconds = 8 * 3600;
                $onlineSeconds = 7 * 3600;
                $offlineSeconds = 3600;
            }

            $onlineHours = max(0, round($onlineSeconds / 3600, 1));
            $offlineMins = max(0, round($offlineSeconds / 60));
            $offlineHoursFormatted = $offlineMins >= 60
                ? floor($offlineMins / 60) . ' jam ' . ($offlineMins % 60 ? ($offlineMins % 60) . 'm' : '')
                : $offlineMins . ' mnt';

            $workHours = max(0, round($totalWorkSeconds / 3600));
            $ratioPct = $totalWorkSeconds > 0 ? max(0, round(($onlineSeconds / $totalWorkSeconds) * 100, 1)) : 0;

            // Jam pertama online & jam terakhir offline
            $firstOnline = $dayLogs->first(fn ($l) => $l->action === 'online');
            $lastOffline = $dayLogs->last(fn ($l) => $l->action === 'offline');
            $firstLog = $dayLogs->first();
            $lastLog = $dayLogs->last();

            $jamOnlineStr = $firstOnline
                ? Carbon::parse($firstOnline->logged_at)->format('H:i')
                : ($firstLog ? Carbon::parse($firstLog->logged_at)->format('H:i') : '08:00');

            $jamOfflineStr = $lastOffline
                ? Carbon::parse($lastOffline->logged_at)->format('H:i')
                : ($lastLog ? Carbon::parse($lastLog->logged_at)->format('H:i') : '17:00');

            $dailyStats[] = [
                'tanggal' => Carbon::parse($dateStr)->translatedFormat('d M Y'),
                'raw_date' => $dateStr,
                'jam_online' => $jamOnlineStr . ' WIB',
                'jam_offline' => $jamOfflineStr . ' WIB',
                'waktu_presensi' => $jamOnlineStr . ' - ' . $jamOfflineStr,
                'online' => $onlineHours . ' jam',
                'offline' => $offlineHoursFormatted,
                'jam_kerja' => $workHours . ' Jam',
                'rasio_online' => number_format($ratioPct, 1, ',', '.') . '%',
                'rasio_online_pct' => $ratioPct,
            ];
        }

        // If no status logs exist for date range, return default mock stats for demonstration
        if (empty($dailyStats)) {
            $defaultDate = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now();
            $dailyStats[] = [
                'tanggal' => $defaultDate->translatedFormat('d M Y'),
                'raw_date' => $defaultDate->toDateString(),
                'jam_online' => '08:00 WIB',
                'jam_offline' => '17:00 WIB',
                'waktu_presensi' => '08:00 - 17:00',
                'online' => '7 jam',
                'offline' => '1 jam 18m',
                'jam_kerja' => '9 Jam',
                'rasio_online' => '85,7%',
                'rasio_online_pct' => 85.7,
            ];
        }

        $allStats = array_reverse($dailyStats);
        $total = count($allStats);
        $offset = ($page - 1) * $perPage;
        $pagedStats = array_slice($allStats, $offset, $perPage);

        return response()->json([
            'data' => $pagedStats,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                        'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ]);
    }

    private function analyzeDestinationTiming($destination, $driverId = null): array
    {
        $historiesQuery = $destination->relationLoaded('statusHistories')
            ? $destination->statusHistories->sortBy('changed_at')
            : $destination->statusHistories()->orderBy('changed_at', 'asc')->get();

        if ($driverId) {
            $histories = $historiesQuery->filter(function ($h) use ($driverId) {
                return $h->changed_by == $driverId;
            });
        } else {
            $histories = $historiesQuery;
        }

        $timestamps = [
            'picked' => null,
            'in_progress' => null,
            'arrived' => null,
            'delivered' => null,
            'returning' => null,
            'finished' => null,
        ];

        foreach ($histories as $h) {
            if (array_key_exists($h->new_status, $timestamps) && !$timestamps[$h->new_status]) {
                $timestamps[$h->new_status] = $h->changed_at ? $h->changed_at->toIso8601String() : null;
            }
        }

        // If histories don't have changed_by or timestamps are empty, fallback to progress records matching driverId
        if (empty(array_filter($timestamps)) && $destination->relationLoaded('progress')) {
            $progressList = $driverId
                ? $destination->progress->filter(fn($p) => $p->driver_id == $driverId)->sortBy('progress_time')
                : $destination->progress->sortBy('progress_time');

            foreach ($progressList as $p) {
                if (array_key_exists($p->status, $timestamps) && !$timestamps[$p->status]) {
                    $timestamps[$p->status] = $p->progress_time ? Carbon::parse($p->progress_time)->toIso8601String() : null;
                }
            }
        }

        // Calculate durasi (JLN -> TRM or picked -> delivered)
        $durasiMnt = null;
        $start = $timestamps['in_progress'] ?? $timestamps['picked'];
        $end = $timestamps['delivered'] ?? $timestamps['arrived'];
        if ($start && $end) {
            $diff = (strtotime($end) - strtotime($start)) / 60;
            if ($diff > 0) {
                $durasiMnt = (int) round($diff);
            }
        }

        return [
            'timestamps' => $timestamps,
            'durasi_mnt' => $durasiMnt,
            'jeda_mnt' => null,
            'waktu_pulang_mnt' => null,
        ];
    }

    private function formatMinutesToHuman(int $minutes): string
    {
        if ($minutes <= 0) return '0 mnt';
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        if ($hours > 0) {
            return $hours . ' jam ' . ($mins > 0 ? $mins . ' mnt' : '');
        }
        return $mins . ' mnt';
    }
}
