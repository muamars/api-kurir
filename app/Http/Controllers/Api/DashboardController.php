<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentProgress;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard Controller - PRIVATE DASHBOARD SYSTEM
 * 
 * ✅ PERUBAHAN DARI UNIVERSAL KE PRIVATE:
 * - Setiap user melihat data dashboard yang berbeda berdasarkan role mereka
 * - Admin: Melihat semua data shipment di sistem
 * - Kurir: Hanya melihat shipment yang assigned ke mereka
 * - User: Hanya melihat shipment yang mereka buat sendiri
 * 
 * ✅ ENDPOINT YANG TERPENGARUH:
 * - GET /api/v1/dashboard - Dashboard utama dengan statistik private
 * - GET /api/v1/dashboard/chart - Chart data private berdasarkan role
 * - GET /api/v1/dashboard/shipment-chart - Shipment chart private berdasarkan role
 * 
 * ✅ ENDPOINT YANG TETAP UNIVERSAL (ANTRIAN):
 * - GET /api/v1/dashboard/shipments-table - Tetap menampilkan semua tiket untuk antrian
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $user     = $request->user();
        $dateFrom = Carbon::parse($request->input('date_from', Carbon::now()->subMonths(3)->format('Y-m-d')))->startOfDay();
        $dateTo   = Carbon::parse($request->input('date_to', Carbon::now()->format('Y-m-d')))->endOfDay();
        $cacheKey = "dashboard.index.{$user->id}.{$dateFrom->format('Y-m-d')}.{$dateTo->format('Y-m-d')}";

        $stats = Cache::remember($cacheKey, 120, function () use ($user, $dateFrom, $dateTo) {
            $data = [
                'shipments'   => $this->getPrivateShipmentStats($user, $dateFrom, $dateTo),
                'deliveries'  => $this->getPrivateDeliveryStats($user, $dateFrom, $dateTo),
                'performance' => $this->getPrivatePerformanceStats($user, $dateFrom, $dateTo),
            ];

            if ($user->hasAnyRole(['Admin', 'Super Admin'])) {
                $data['admin'] = $this->getAdminStats($dateFrom, $dateTo);
            } elseif ($user->hasRole('Kurir')) {
                $data['driver'] = $this->getDriverStats($user, $dateFrom, $dateTo);
            } else {
                $data['user'] = $this->getUserStats($user, $dateFrom, $dateTo);
            }

            return $data;
        });

        return response()->json([
            'data' => $stats,
            'meta' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to'   => $dateTo->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Hapus cache dashboard untuk user tertentu.
     * Dipanggil setiap ada perubahan status shipment.
     */
    public static function invalidateFor(array $userIds): void
    {
        foreach (array_unique(array_filter($userIds)) as $id) {
            Cache::forget("dashboard.index.{$id}");
            $today = Carbon::now()->format('Y-m-d');
            $threeMonthsAgo = Carbon::now()->subMonths(3)->format('Y-m-d');
            Cache::forget("dashboard.index.{$id}.{$threeMonthsAgo}.{$today}");

            try {
                if (config('cache.default') === 'redis') {
                    $keys = \Illuminate\Support\Facades\Redis::keys("*dashboard.index.{$id}*");
                    foreach ($keys as $key) {
                        \Illuminate\Support\Facades\Redis::del($key);
                    }
                }
            } catch (\Throwable $e) {}

            foreach (['week', 'month', 'year'] as $period) {
                Cache::forget("dashboard.chart.{$id}.{$period}");
            }
        }
    }

    private function getPrivateShipmentStats($user, Carbon $dateFrom, Carbon $dateTo): array
    {
        $todayStr = today()->format('Y-m-d');

        $query = Shipment::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if (! $user->hasAnyRole(['Admin', 'Super Admin'])) {
            if ($user->hasRole('Kurir')) {
                $query->where('assigned_driver_id', $user->id);
            } else {
                $query->where('created_by', $user->id);
            }
        }

        $res = $query->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent
        ", [$todayStr])->first();

        return [
            'total' => (int) ($res->total ?? 0),
            'today' => (int) ($res->today ?? 0),
            'pending' => (int) ($res->pending ?? 0),
            'approved' => (int) ($res->approved ?? 0),
            'assigned' => (int) ($res->assigned ?? 0),
            'in_progress' => (int) ($res->in_progress ?? 0),
            'completed' => (int) ($res->completed ?? 0),
            'cancelled' => (int) ($res->cancelled ?? 0),
            'urgent' => (int) ($res->urgent ?? 0),
        ];
    }

    private function getPrivateDeliveryStats($user, Carbon $dateFrom, Carbon $dateTo): array
    {
        $todayStr = now()->startOfDay()->format('Y-m-d H:i:s');
        $thisWeekStr = now()->startOfWeek()->format('Y-m-d H:i:s');
        $thisMonthStr = now()->startOfMonth()->format('Y-m-d H:i:s');

        $query = Shipment::query()
            ->where('status', 'completed')
            ->whereBetween('updated_at', [$dateFrom, $dateTo]);

        if (! $user->hasAnyRole(['Admin', 'Super Admin'])) {
            if ($user->hasRole('Kurir')) {
                $query->where('assigned_driver_id', $user->id);
            } else {
                $query->where('created_by', $user->id);
            }
        }

        $res = $query->selectRaw("
            SUM(CASE WHEN updated_at >= ? THEN 1 ELSE 0 END) as today,
            SUM(CASE WHEN updated_at >= ? THEN 1 ELSE 0 END) as this_week,
            SUM(CASE WHEN updated_at >= ? THEN 1 ELSE 0 END) as this_month,
            COUNT(*) as in_range
        ", [$todayStr, $thisWeekStr, $thisMonthStr])->first();

        return [
            'today' => (int) ($res->today ?? 0),
            'this_week' => (int) ($res->this_week ?? 0),
            'this_month' => (int) ($res->this_month ?? 0),
            'in_range' => (int) ($res->in_range ?? 0),
        ];
    }

    private function getPrivatePerformanceStats($user, Carbon $dateFrom, Carbon $dateTo): array
    {
        $query = Shipment::query()
            ->whereBetween('updated_at', [$dateFrom, $dateTo]);

        if (! $user->hasAnyRole(['Admin', 'Super Admin'])) {
            if ($user->hasRole('Kurir')) {
                $query->where('assigned_driver_id', $user->id);
            } else {
                $query->where('created_by', $user->id);
            }
        }

        $res = $query->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'completed' AND deadline IS NOT NULL AND updated_at <= deadline THEN 1 ELSE 0 END) as on_time
        ")->first();

        $total = (int) ($res->total ?? 0);
        $completed = (int) ($res->completed ?? 0);
        $onTime = (int) ($res->on_time ?? 0);

        return [
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'on_time_rate' => $completed > 0 ? round(($onTime / $completed) * 100, 2) : 0,
        ];
    }

    private function getAdminStats(Carbon $dateFrom, Carbon $dateTo): array
    {
        return [
            // Pending approval & driver/user counts merepresentasikan state SEKARANG,
            // bukan histori — tidak ikut difilter rentang tanggal.
            'pending_approvals' => Shipment::where('status', 'pending')->count(),
            'unassigned_shipments' => Shipment::where('status', 'approved')
                ->whereNull('assigned_driver_id')->count(),
            'active_drivers' => User::role('Kurir')->where('is_active', true)->count(),
            'standby_drivers' => User::role('Kurir')
                ->where('is_active', true)
                ->whereDoesntHave('assignedShipments', function ($q) {
                    $q->whereIn('status', ['assigned', 'in_progress']);
                })
                ->count(),
            'total_users' => User::where('is_active', true)->count(),
            'recent_shipments' => Shipment::with(['creator', 'driver'])
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'shipment_id', 'status', 'priority', 'created_by', 'assigned_driver_id', 'created_at']),
        ];
    }

    private function getDriverStats($user, Carbon $dateFrom, Carbon $dateTo): array
    {
        $today = now()->startOfDay();

        return [
            // Status "sekarang" (tugas berjalan) — tidak ikut difilter rentang tanggal.
            'assigned_today' => Shipment::where('assigned_driver_id', $user->id)
                ->whereDate('approved_at', $today)->count(),
            'in_progress' => Shipment::where('assigned_driver_id', $user->id)
                ->where('status', 'in_progress')->count(),
            'completed_today' => Shipment::where('assigned_driver_id', $user->id)
                ->where('status', 'completed')
                ->whereDate('updated_at', $today)->count(),
            'pending_destinations' => DB::table('shipment_destinations')
                ->join('shipments', 'shipment_destinations.shipment_id', '=', 'shipments.id')
                ->where('shipments.assigned_driver_id', $user->id)
                ->where('shipment_destinations.status', 'pending')
                ->count(),
            'completed_in_range' => Shipment::where('assigned_driver_id', $user->id)
                ->where('status', 'completed')
                ->whereBetween('updated_at', [$dateFrom, $dateTo])
                ->count(),
        ];
    }

    private function getUserStats($user, Carbon $dateFrom, Carbon $dateTo): array
    {
        return [
            'my_shipments' => Shipment::where('created_by', $user->id)
                ->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'pending_approval' => Shipment::where('created_by', $user->id)
                ->where('status', 'pending')
                ->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'in_delivery' => Shipment::where('created_by', $user->id)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'completed_this_month' => Shipment::where('created_by', $user->id)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
        ];
    }

    public function getChartData(Request $request): JsonResponse
    {
        $period   = $request->get('period', 'week'); // week, month, year
        $user     = $request->user();
        $cacheKey = "dashboard.chart.{$user->id}.{$period}";
        $ttl      = 300; // 5 menit — chart mingguan/bulanan jarang berubah drastis

        $data = Cache::remember($cacheKey, $ttl, function () use ($period, $user) {
            return match ($period) {
                'week'  => $this->getWeeklyChartData($user),
                'month' => $this->getMonthlyChartData($user),
                'year'  => $this->getYearlyChartData($user),
                default => [],
            };
        });

        return response()->json(['data' => $data]);
    }

    private function getWeeklyChartData($user): array
    {
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();

        // ✅ PRIVATE DASHBOARD: Chart data berbeda berdasarkan role user
        if ($user->hasAnyRole(['Admin', 'Super Admin'])) {
            // Admin melihat semua data
            $query = Shipment::whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($user->hasRole('Kurir')) {
            // Kurir hanya melihat shipment yang assigned ke mereka
            $query = Shipment::where('assigned_driver_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            // User biasa hanya melihat shipment yang mereka buat
            $query = Shipment::where('created_by', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate]);
        }

        $data = $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing dates with 0
        $result = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $count = $data->firstWhere('date', $dateStr)?->count ?? 0;
            $result[] = [
                'date' => $dateStr,
                'day' => $date->format('l'),
                'count' => $count,
            ];
        }

        return $result;
    }

    private function getMonthlyChartData($user): array
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        // ✅ PRIVATE DASHBOARD: Chart data berbeda berdasarkan role user
        if ($user->hasAnyRole(['Admin', 'Super Admin'])) {
            // Admin melihat semua data
            $query = Shipment::whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($user->hasRole('Kurir')) {
            // Kurir hanya melihat shipment yang assigned ke mereka
            $query = Shipment::where('assigned_driver_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            // User biasa hanya melihat shipment yang mereka buat
            $query = Shipment::where('created_by', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate]);
        }

        return $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getYearlyChartData($user): array
    {
        $startDate = now()->startOfYear();
        $endDate = now()->endOfYear();

        // ✅ PRIVATE DASHBOARD: Chart data berbeda berdasarkan role user
        if ($user->hasAnyRole(['Admin', 'Super Admin'])) {
            // Admin melihat semua data
            $query = Shipment::whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($user->hasRole('Kurir')) {
            // Kurir hanya melihat shipment yang assigned ke mereka
            $query = Shipment::where('assigned_driver_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            // User biasa hanya melihat shipment yang mereka buat
            $query = Shipment::where('created_by', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate]);
        }

        return $query->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'month_name' => now()->month($item->month)->format('F'),
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get comprehensive shipment chart data for analytics
     */
    public function getShipmentChartData(Request $request): JsonResponse
    {
        $request->validate([
            'chart_type'      => 'required|in:daily,monthly,yearly,category,tugas_pengiriman,vehicle_type,customer,status,priority,total',
            'date_from'       => 'nullable|date',
            'date_to'         => 'nullable|date|after_or_equal:date_from',
            'period'          => 'nullable|in:day,week,month,year',
            'category_id'     => 'nullable|exists:shipment_categories,id',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
        ]);

        $user      = $request->user();
        $chartType = $request->chart_type;

        // Cache analytics — admin 15 menit, user/kurir 5 menit
        $ttl      = $user->hasAnyRole(['Admin', 'Super Admin']) ? 900 : 300;
        $cacheKey = 'dashboard.shipment_chart.' . $user->id . '.' . md5(json_encode($request->only([
            'chart_type', 'date_from', 'date_to', 'period', 'category_id', 'vehicle_type_id',
        ])));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        // ✅ PRIVATE DASHBOARD: Base query berbeda berdasarkan role user
        $query = Shipment::with(['category', 'vehicleType', 'creator.division']);
        
        if ($user->hasAnyRole(['Admin', 'Super Admin'])) {
            // Admin melihat semua data
            // Query tetap tanpa filter
        } elseif ($user->hasRole('Kurir')) {
            // Kurir hanya melihat shipment yang assigned ke mereka
            $query->where('assigned_driver_id', $user->id);
        } else {
            // User biasa hanya melihat shipment yang mereka buat
            $query->where('created_by', $user->id);
        }

        // Date filtering — falls back to the last 3 months when the caller gives no
        // date_from/date_to/period, to avoid an unbounded scan/aggregate over the
        // entire shipments table for Admin users. Column is prefixed with the table
        // name because some chart_type branches below join in other tables that also
        // have a created_at column (ambiguous-column error otherwise).
        $this->applyDateFilter($query, $request);
        if (!$request->date_from && !$request->date_to && !$request->period) {
            $query->whereBetween('shipments.created_at', [now()->subMonths(3)->startOfDay(), now()->endOfDay()]);
        }

        // Additional filters
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->vehicle_type_id) {
            $query->where('vehicle_type_id', $request->vehicle_type_id);
        }

        $data = [];

        switch ($chartType) {
            case 'daily':
                $data = $this->getDailyChartData($query, $request);
                break;
            case 'monthly':
                $data = $this->getMonthlyShipmentChartData($query, $request);
                break;
            case 'yearly':
                $data = $this->getYearlyShipmentChartData($query, $request);
                break;
            case 'category':
                $data = $this->getCategoryChartData($query);
                break;
            case 'tugas_pengiriman':
                $data = $this->getTugasPengirimanChartData($query);
                break;
            case 'vehicle_type':
                $data = $this->getVehicleTypeChartData($query);
                break;
            case 'customer':
                $data = $this->getCustomerChartData($query);
                break;
            case 'status':
                $data = $this->getStatusChartData($query);
                break;
            case 'priority':
                $data = $this->getPriorityChartData($query);
                break;
            case 'total':
                $data = $this->getTotalShipmentData($query, $request);
                break;
        }

        $response = [
            'data' => $data,
            'meta' => [
                'chart_type'      => $chartType,
                'period'          => $request->period ?? 'custom',
                'date_range'      => [
                    'from' => $request->date_from ?? 'All time',
                    'to'   => $request->date_to   ?? 'All time',
                ],
                'filters_applied' => [
                    'category_id'     => $request->category_id,
                    'vehicle_type_id' => $request->vehicle_type_id,
                ],
                'user_scope' => $user->hasAnyRole(['Admin', 'Super Admin'])
                    ? 'all_data'
                    : ($user->hasRole('Kurir') ? 'assigned_shipments' : 'own_shipments'),
            ],
        ];

        Cache::put($cacheKey, $response, $ttl);

        return response()->json($response);
    }

    private function applyDateFilter($query, $request): void
    {
        if ($request->date_from && $request->date_to) {
            $query->whereBetween('created_at', [$request->date_from, $request->date_to]);
        } elseif ($request->period) {
            switch ($request->period) {
                case 'day':
                    $query->whereDate('created_at', now()->format('Y-m-d'));
                    break;
                case 'week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                    break;
                case 'year':
                    $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
                    break;
            }
        }
    }

    private function getDailyChartData($query, $request): array
    {
        // Get date range
        $startDate = $request->date_from ? Carbon::parse($request->date_from) : now()->startOfMonth();
        $endDate = $request->date_to ? Carbon::parse($request->date_to) : now()->endOfMonth();

        $data = $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $result[] = [
                'date' => $dateStr,
                'day_name' => $date->format('l'),
                'count' => $data->get($dateStr)?->count ?? 0,
                'formatted_date' => $date->format('d M Y'),
            ];
        }

        return $result;
    }

    private function getMonthlyShipmentChartData($query, $request): array
    {
        $year = $request->year ?? now()->year;
        
        $data = $query->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $result = [];
        for ($month = 1; $month <= 12; $month++) {
            $result[] = [
                'month' => $month,
                'month_name' => Carbon::create()->month($month)->format('F'),
                'month_short' => Carbon::create()->month($month)->format('M'),
                'count' => $data->get($month)?->count ?? 0,
                'year' => $year,
            ];
        }

        return $result;
    }

    private function getYearlyShipmentChartData($query, $request): array
    {
        $startYear = $request->start_year ?? (now()->year - 4);
        $endYear = $request->end_year ?? now()->year;

        $data = $query->selectRaw('YEAR(created_at) as year, COUNT(*) as count')
            ->whereBetween(\DB::raw('YEAR(created_at)'), [$startYear, $endYear])
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->keyBy('year');

        $result = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $result[] = [
                'year' => $year,
                'count' => $data->get($year)?->count ?? 0,
            ];
        }

        return $result;
    }

    private function getCategoryChartData($query): array
    {
        // Clone query untuk menghitung total
        $totalQuery = clone $query;
        $total = $totalQuery->count();
        
        $data = $query->join('shipment_categories', 'shipments.category_id', '=', 'shipment_categories.id')
            ->selectRaw('shipment_categories.id, shipment_categories.name, COUNT(shipments.id) as count')
            ->groupBy('shipment_categories.id', 'shipment_categories.name')
            ->orderBy('count', 'desc')
            ->get();

        return $data->map(function ($item) use ($total) {
            return [
                'category_id' => $item->id,
                'category_name' => $item->name,
                'count' => (int) $item->count,
                'percentage' => $total > 0 ? round(($item->count / $total) * 100, 2) : 0,
            ];
        })->toArray();
    }

    private function getTugasPengirimanChartData($query): array
    {
        // Clone query untuk menghitung total
        $totalQuery = clone $query;
        $total = $totalQuery->count();

        $data = $query->join('tugas_pengiriman', 'shipments.tugas_pengiriman_id', '=', 'tugas_pengiriman.id')
            ->selectRaw('tugas_pengiriman.id, tugas_pengiriman.tugas, COUNT(shipments.id) as count')
            ->groupBy('tugas_pengiriman.id', 'tugas_pengiriman.tugas')
            ->orderBy('count', 'desc')
            ->get();

        return $data->map(function ($item) use ($total) {
            return [
                'tugas_pengiriman_id' => $item->id,
                'tugas_name' => $item->tugas,
                'count' => (int) $item->count,
                'percentage' => $total > 0 ? round(($item->count / $total) * 100, 2) : 0,
            ];
        })->toArray();
    }

    private function getVehicleTypeChartData($query): array
    {
        // Clone query untuk menghitung total
        $totalQuery = clone $query;
        $total = $totalQuery->count();
        
        $data = $query->join('vehicle_types', 'shipments.vehicle_type_id', '=', 'vehicle_types.id')
            ->selectRaw('vehicle_types.id, vehicle_types.name, COUNT(shipments.id) as count')
            ->groupBy('vehicle_types.id', 'vehicle_types.name')
            ->orderBy('count', 'desc')
            ->get();

        return $data->map(function ($item) use ($total) {
            return [
                'vehicle_type_id' => $item->id,
                'vehicle_type_name' => $item->name,
                'count' => (int) $item->count,
                'percentage' => $total > 0 ? round(($item->count / $total) * 100, 2) : 0,
            ];
        })->toArray();
    }

    private function getCustomerChartData($query): array
    {
        // Clone query untuk menghitung total
        $totalQuery = clone $query;
        $total = $totalQuery->count();
        
        // Group by creator (customer/user who created shipment)
        $data = $query->join('users', 'shipments.created_by', '=', 'users.id')
            ->leftJoin('divisions', 'users.division_id', '=', 'divisions.id')
            ->selectRaw('users.id, users.name, divisions.name as division_name, COUNT(shipments.id) as count')
            ->groupBy('users.id', 'users.name', 'divisions.name')
            ->orderBy('count', 'desc')
            ->limit(20) // Top 20 customers
            ->get();

        return $data->map(function ($item) use ($total) {
            return [
                'customer_id' => $item->id,
                'customer_name' => $item->name,
                'division_name' => $item->division_name ?? 'No Division',
                'count' => (int) $item->count,
                'percentage' => $total > 0 ? round(($item->count / $total) * 100, 2) : 0,
            ];
        })->toArray();
    }

    private function getStatusChartData($query): array
    {
        // Clone query untuk menghitung total
        $totalQuery = clone $query;
        $total = $totalQuery->count();
        
        $data = $query->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('count', 'desc')
            ->get();

        return $data->map(function ($item) use ($total) {
            return [
                'status' => $item->status,
                'status_label' => $this->getStatusLabel($item->status),
                'count' => (int) $item->count,
                'percentage' => $total > 0 ? round(($item->count / $total) * 100, 2) : 0,
            ];
        })->toArray();
    }

    private function getPriorityChartData($query): array
    {
        // Clone query untuk menghitung total
        $totalQuery = clone $query;
        $total = $totalQuery->count();
        
        $data = $query->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->orderBy('count', 'desc')
            ->get();

        return $data->map(function ($item) use ($total) {
            return [
                'priority' => $item->priority,
                'priority_label' => ucfirst($item->priority),
                'count' => (int) $item->count,
                'percentage' => $total > 0 ? round(($item->count / $total) * 100, 2) : 0,
            ];
        })->toArray();
    }

    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu Persetujuan',
            'assigned' => 'Ditugaskan',
            'in_progress' => 'Dalam Perjalanan',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($status),
        };
    }

    private function getTotalShipmentData($query, $request): array
    {
        // Clone query untuk multiple uses
        $baseQuery = clone $query;
        
        // Get total count
        $totalShipments = $baseQuery->count();
        
        // Get breakdown by different dimensions
        $statusBreakdown = (clone $query)->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $priorityBreakdown = (clone $query)->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->get()
            ->keyBy('priority');

        // Get category breakdown
        $categoryBreakdown = (clone $query)->join('shipment_categories', 'shipments.category_id', '=', 'shipment_categories.id')
            ->selectRaw('shipment_categories.name as category_name, COUNT(*) as count')
            ->groupBy('shipment_categories.name')
            ->orderBy('count', 'desc')
            ->limit(5) // Top 5 categories
            ->get();

        // Get vehicle type breakdown
        $vehicleBreakdown = (clone $query)->join('vehicle_types', 'shipments.vehicle_type_id', '=', 'vehicle_types.id')
            ->selectRaw('vehicle_types.name as vehicle_name, COUNT(*) as count')
            ->groupBy('vehicle_types.name')
            ->orderBy('count', 'desc')
            ->get();

        // Get time-based summary
        $timeBreakdown = [];
        if ($request->period || ($request->date_from && $request->date_to)) {
            $timeBreakdown = [
                'today' => (clone $query)->whereDate('created_at', now()->format('Y-m-d'))->count(),
                'this_week' => (clone $query)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'this_month' => (clone $query)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'this_year' => (clone $query)->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()])->count(),
            ];
        }

        return [
            'total_shipments' => $totalShipments,
            'summary' => [
                'completed' => $statusBreakdown->get('completed')?->count ?? 0,
                'in_progress' => $statusBreakdown->get('in_progress')?->count ?? 0,
                'pending' => ($statusBreakdown->get('pending')?->count ?? 0) + ($statusBreakdown->get('assigned')?->count ?? 0),
                'cancelled' => $statusBreakdown->get('cancelled')?->count ?? 0,
                'urgent' => $priorityBreakdown->get('urgent')?->count ?? 0,
                'regular' => $priorityBreakdown->get('regular')?->count ?? 0,
            ],
            'status_breakdown' => [
                'pending' => [
                    'count' => $statusBreakdown->get('pending')?->count ?? 0,
                    'label' => 'Menunggu Persetujuan',
                    'percentage' => $totalShipments > 0 ? round((($statusBreakdown->get('pending')?->count ?? 0) / $totalShipments) * 100, 2) : 0,
                ],
                'assigned' => [
                    'count' => $statusBreakdown->get('assigned')?->count ?? 0,
                    'label' => 'Ditugaskan',
                    'percentage' => $totalShipments > 0 ? round((($statusBreakdown->get('assigned')?->count ?? 0) / $totalShipments) * 100, 2) : 0,
                ],
                'in_progress' => [
                    'count' => $statusBreakdown->get('in_progress')?->count ?? 0,
                    'label' => 'Dalam Perjalanan',
                    'percentage' => $totalShipments > 0 ? round((($statusBreakdown->get('in_progress')?->count ?? 0) / $totalShipments) * 100, 2) : 0,
                ],
                'completed' => [
                    'count' => $statusBreakdown->get('completed')?->count ?? 0,
                    'label' => 'Selesai',
                    'percentage' => $totalShipments > 0 ? round((($statusBreakdown->get('completed')?->count ?? 0) / $totalShipments) * 100, 2) : 0,
                ],
                'cancelled' => [
                    'count' => $statusBreakdown->get('cancelled')?->count ?? 0,
                    'label' => 'Dibatalkan',
                    'percentage' => $totalShipments > 0 ? round((($statusBreakdown->get('cancelled')?->count ?? 0) / $totalShipments) * 100, 2) : 0,
                ],
            ],
            'priority_breakdown' => [
                'urgent' => [
                    'count' => $priorityBreakdown->get('urgent')?->count ?? 0,
                    'label' => 'Urgent',
                    'percentage' => $totalShipments > 0 ? round((($priorityBreakdown->get('urgent')?->count ?? 0) / $totalShipments) * 100, 2) : 0,
                ],
                'regular' => [
                    'count' => $priorityBreakdown->get('regular')?->count ?? 0,
                    'label' => 'Regular',
                    'percentage' => $totalShipments > 0 ? round((($priorityBreakdown->get('regular')?->count ?? 0) / $totalShipments) * 100, 2) : 0,
                ],
            ],
            'top_categories' => $categoryBreakdown->map(function ($item) use ($totalShipments) {
                return [
                    'category_name' => $item->category_name,
                    'count' => $item->count,
                    'percentage' => $totalShipments > 0 ? round(($item->count / $totalShipments) * 100, 2) : 0,
                ];
            })->toArray(),
            'vehicle_types' => $vehicleBreakdown->map(function ($item) use ($totalShipments) {
                return [
                    'vehicle_name' => $item->vehicle_name,
                    'count' => $item->count,
                    'percentage' => $totalShipments > 0 ? round(($item->count / $totalShipments) * 100, 2) : 0,
                ];
            })->toArray(),
            'time_breakdown' => $timeBreakdown,
            'completion_rate' => $totalShipments > 0 ? round((($statusBreakdown->get('completed')?->count ?? 0) / $totalShipments) * 100, 2) : 0,
        ];
    }

    /**
     * Get all shipments table with pagination for dashboard - ANTRIAN TIKET
     * Endpoint ini bisa diakses oleh semua user untuk melihat antrian tiket
     */
    public function getShipmentsTable(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $request->validate([
                'per_page' => 'nullable|integer|min:1|max:500',
                'status' => 'nullable|in:pending,approved,assigned,in_progress,completed,cancelled,all',
                'priority' => 'nullable|in:regular,urgent,all',
                'search' => 'nullable|string|max:255',
                'sort_by' => 'nullable|in:created_at,shipment_id,status,priority,deadline',
                'sort_order' => 'nullable|in:asc,desc',
                'show_completed' => 'nullable|boolean', // ✅ NEW: Parameter untuk tampilkan completed
            ]);

            // ✅ ANTRIAN TIKET: Tampilkan SEMUA tiket untuk semua user
            // Tidak ada role-based filtering - semua user bisa lihat semua tiket
            $query = Shipment::with([
                'creator:id,name,email',
                'driver:id,name,email',
                'category:id,name',
                'vehicleType:id,name',
                'destinations:id,shipment_id,delivery_address,receiver_name,receiver_company,status'
            ]);

            // ✅ AUTO-HIDE COMPLETED & CANCELLED: Secara default sembunyikan tiket completed dan cancelled
            if (!$request->get('show_completed', false)) {
                $query->whereNotIn('status', ['completed', 'cancelled']);
            }

            // Filter berdasarkan status jika diminta
            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter berdasarkan priority jika diminta
            if ($request->filled('priority') && $request->priority !== 'all') {
                $query->where('priority', $request->priority);
            }

            // Search functionality
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('shipment_id', 'LIKE', "%{$search}%")
                        ->orWhere('notes', 'LIKE', "%{$search}%")
                        ->orWhereHas('creator', function ($creatorQuery) use ($search) {
                            $creatorQuery->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('email', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('destinations', function ($destQuery) use ($search) {
                            $destQuery->where('receiver_name', 'LIKE', "%{$search}%")
                                ->orWhere('delivery_address', 'LIKE', "%{$search}%");
                        });
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            if ($sortBy === 'priority') {
                // Sort urgent first, then regular
                $query->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END " . $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Default secondary sort by created_at desc
            if ($sortBy !== 'created_at') {
                $query->orderBy('created_at', 'desc');
            }

            // Pagination
            $perPage = $request->get('per_page', 500);
            $shipments = $query->paginate($perPage);

            // Transform data untuk antrian tiket
            $tableData = $shipments->getCollection()->map(function ($shipment) {
                return [
                    'id' => $shipment->id,
                    'shipment_id' => $shipment->shipment_id,
                    'status' => $shipment->status,
                    'status_label' => $this->getStatusLabel($shipment->status),
                    'status_color' => $this->getStatusColor($shipment->status),
                    'priority' => $shipment->priority,
                    'priority_label' => ucfirst($shipment->priority),
                    'priority_color' => $shipment->priority === 'urgent' ? 'red' : 'blue',
                    'notes' => $shipment->notes,
                    'deadline' => $shipment->deadline ? $shipment->deadline->format('Y-m-d H:i') : null,
                    'deadline_formatted' => $shipment->deadline ? $shipment->deadline->format('d M Y, H:i') : null,
                    'is_overdue' => $shipment->deadline && $shipment->deadline < now() && $shipment->status !== 'completed',
                    'created_at' => $shipment->created_at->format('Y-m-d H:i:s'),
                    'created_at_formatted' => $shipment->created_at->format('d M Y, H:i'),
                    'created_at_human' => $shipment->created_at->diffForHumans(),
                    
                    // Creator info
                    'creator' => $shipment->creator ? [
                        'id' => $shipment->creator->id,
                        'name' => $shipment->creator->name,
                        'email' => $shipment->creator->email,
                    ] : null,
                    
                    // Driver info
                    'driver' => $shipment->driver ? [
                        'id' => $shipment->driver->id,
                        'name' => $shipment->driver->name,
                        'email' => $shipment->driver->email,
                    ] : null,
                    
                    // Category info
                    'category' => $shipment->category ? [
                        'id' => $shipment->category->id,
                        'name' => $shipment->category->name,
                    ] : null,
                    
                    // Vehicle type info
                    'vehicle_type' => $shipment->vehicleType ? [
                        'id' => $shipment->vehicleType->id,
                        'name' => $shipment->vehicleType->name,
                    ] : null,
                    
                    // Destinations summary
                    'destinations_count' => $shipment->destinations->count(),
                    'destinations_summary' => $shipment->destinations->map(function ($dest) {
                        return [
                            'id' => $dest->id,
                            'delivery_address' => $dest->delivery_address,
                            'receiver_name' => $dest->receiver_name,
                            'receiver_company' => $dest->receiver_company ?? '',
                            'status' => $dest->status,
                            'status_label' => $this->getDestinationStatusLabel($dest->status),
                        ];
                    })->take(3), // Hanya tampilkan 3 destination pertama untuk ringkasan
                    
                    // Quick actions available
                    'can_edit' => true, // Semua user bisa lihat, tapi edit tergantung role
                    'can_assign' => $shipment->status === 'pending',
                    'can_cancel' => in_array($shipment->status, ['pending', 'assigned']),
                ];
            });

            // Summary statistics untuk antrian (exclude completed by default).
            // Cached briefly (30s) since this previously ran 9 separate full-table
            // COUNT queries (including one literal duplicate) on every single request.
            $showCompleted = $request->get('show_completed', false);
            $summary = Cache::remember(
                "dashboard:shipments-table:summary:{$showCompleted}",
                30,
                function () use ($showCompleted) {
                    // One query for all status counts, one for all priority counts.
                    $statusCounts = Shipment::select('status')
                        ->selectRaw('count(*) as total')
                        ->groupBy('status')
                        ->pluck('total', 'status');

                    $priorityCounts = Shipment::select('priority')
                        ->selectRaw('count(*) as total')
                        ->groupBy('priority')
                        ->pluck('total', 'priority');

                    $totalInSystem = $statusCounts->sum();
                    $completedCount = $statusCounts->get('completed', 0);

                    $overdueCount = Shipment::where('deadline', '<', now())
                        ->whereNotIn('status', ['completed', 'cancelled'])
                        ->count();

                    return [
                        'total_in_system' => $totalInSystem,
                        'status_counts' => [
                            'pending' => $statusCounts->get('pending', 0),
                            'assigned' => $statusCounts->get('assigned', 0),
                            'in_progress' => $statusCounts->get('in_progress', 0),
                            'completed' => $completedCount,
                            'cancelled' => $statusCounts->get('cancelled', 0),
                        ],
                        'priority_counts' => [
                            'urgent' => $priorityCounts->get('urgent', 0),
                            'regular' => $priorityCounts->get('regular', 0),
                        ],
                        'overdue_count' => $overdueCount,
                        'completed_hidden' => !$showCompleted,
                        'completed_count' => $completedCount,
                    ];
                }
            );
            $summary['total_shipments'] = $shipments->total();

            return response()->json([
                'message' => 'Antrian tiket berhasil diambil',
                'data' => $tableData,
                'summary' => $summary,
                'pagination' => [
                    'current_page' => $shipments->currentPage(),
                    'per_page' => $shipments->perPage(),
                    'total' => $shipments->total(),
                    'last_page' => $shipments->lastPage(),
                    'from' => $shipments->firstItem(),
                    'to' => $shipments->lastItem(),
                    'has_more_pages' => $shipments->hasMorePages(),
                ],
                'filters' => [
                    'status' => $request->status ?? 'all',
                    'priority' => $request->priority ?? 'all',
                    'search' => $request->search ?? '',
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
                'user_info' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_roles' => $user->getRoleNames(),
                    'can_see_all_tickets' => true, // Semua user bisa lihat semua tiket
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data antrian tiket',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Get destination status label in Indonesian
     */
    private function getDestinationStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu Pickup',
            'picked' => 'Sudah Dipickup',
            'in_progress' => 'Dalam Perjalanan',
            'arrived' => 'Sampai di Lokasi',
            'delivered' => 'Sudah Diterima',
            'returning' => 'Perjalanan Pulang',
            'finished' => 'Sampai di Kantor',
            'takeover' => 'Takeover',
            'failed' => 'Gagal',
            default => ucfirst($status),
        };
    }

    /**
     * Get status color for UI display
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'yellow',
            'approved' => 'blue',
            'assigned' => 'indigo',
            'in_progress' => 'purple',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get shipping service analysis report (Online vs Internal Courier)
     * Endpoint untuk analisa pengiriman berdasarkan jenis layanan
     * 
     * ✅ LOGIKA BARU:
     * - Internal Courier: Shipment yang di-assign via bulk-assign-driver (ada assigned_driver_id)
     * - Online Courier: Shipment yang di-complete via complete-shipments (ada vehicle_used & shipping_cost)
     */
    public function getShippingServiceReport(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'period' => 'nullable|in:today,week,month,quarter,year,custom',
            'group_by' => 'nullable|in:day,week,month,year',
        ]);

        $user = $request->user();

        // Base query berdasarkan role user - hanya shipment completed
        $query = Shipment::query()->where('status', 'completed');

        if ($user->hasAnyRole(['Admin', 'Super Admin'])) {
            // Admin melihat semua data
        } elseif ($user->hasRole('Kurir')) {
            // Kurir hanya melihat shipment yang assigned ke mereka
            $query->where('assigned_driver_id', $user->id);
        } else {
            // User biasa hanya melihat shipment yang mereka buat
            $query->where('created_by', $user->id);
        }

        // Apply date filter — falls back to the last 3 months when the caller gives
        // no date_from/date_to/period, so this never does an unbounded full-table
        // fetch of every completed shipment ever (which grows without bound).
        $hasExplicitFilter = ($request->date_from && $request->date_to) || $request->period;
        $this->applyDateFilterForReport($query, $request);
        if (!$hasExplicitFilter) {
            $query->whereBetween('completed_at', [now()->subMonths(3)->startOfDay(), now()->endOfDay()]);
        }

        // Get data dengan informasi lengkap
        $shipments = $query->select([
            'id', 'shipment_id', 'vehicle_used', 'shipping_cost', 
            'assigned_driver_id', 'completed_at', 'completed_by', 'created_at'
        ])
        ->with(['driver:id,name', 'completedBy:id,name'])
        ->get();

        // ✅ KATEGORISASI BARU berdasarkan logika bisnis yang tepat
        $internalServices = $this->categorizeInternalServicesNew($shipments);
        $onlineServices = $this->categorizeOnlineServicesNew($shipments);

        // Hitung statistik
        $totalShipments = $shipments->count();
        $totalOnline = $onlineServices->count();
        $totalInternal = $internalServices->count();
        
        $totalCostOnline = $onlineServices->sum('shipping_cost') ?? 0;
        $totalCostInternal = $internalServices->sum('shipping_cost') ?? 0;
        $totalCost = $totalCostOnline + $totalCostInternal;

        // Breakdown berdasarkan periode jika diminta
        $timeBreakdown = [];
        if ($request->group_by) {
            $timeBreakdown = $this->getTimeBreakdownForServicesNew($internalServices, $onlineServices, $request->group_by);
        }

        // Vehicle analysis dengan logika baru
        $vehicleAnalysis = $this->getVehicleAnalysisNew($shipments);

        return response()->json([
            'data' => [
                'summary' => [
                    'total_shipments' => $totalShipments,
                    'total_cost' => $totalCost,
                    'average_cost_per_shipment' => $totalShipments > 0 ? round($totalCost / $totalShipments, 2) : 0,
                ],
                'service_comparison' => [
                    'online_courier' => [
                        'count' => $totalOnline,
                        'percentage' => $totalShipments > 0 ? round(($totalOnline / $totalShipments) * 100, 2) : 0,
                        'total_cost' => $totalCostOnline,
                        'average_cost' => $totalOnline > 0 ? round($totalCostOnline / $totalOnline, 2) : 0,
                        'cost_percentage' => $totalCost > 0 ? round(($totalCostOnline / $totalCost) * 100, 2) : 0,
                        'description' => 'Shipment yang diselesaikan via complete-shipments endpoint (ada vehicle_used & shipping_cost)',
                    ],
                    'internal_courier' => [
                        'count' => $totalInternal,
                        'percentage' => $totalShipments > 0 ? round(($totalInternal / $totalShipments) * 100, 2) : 0,
                        'total_cost' => $totalCostInternal,
                        'average_cost' => $totalInternal > 0 ? round($totalCostInternal / $totalInternal, 2) : 0,
                        'cost_percentage' => $totalCost > 0 ? round(($totalCostInternal / $totalCost) * 100, 2) : 0,
                        'description' => 'Shipment yang di-assign via bulk-assign-driver endpoint (ada assigned_driver_id)',
                    ],
                ],
                'vehicle_analysis' => $vehicleAnalysis,
                'time_breakdown' => $timeBreakdown,
                'detailed_breakdown' => [
                    'online_services' => $this->getDetailedServiceBreakdownNew($onlineServices, 'online'),
                    'internal_services' => $this->getDetailedServiceBreakdownNew($internalServices, 'internal'),
                ],
            ],
            'meta' => [
                'period' => $request->period ?? 'custom',
                'date_range' => [
                    'from' => $request->date_from ?? ($hasExplicitFilter ? 'All time' : now()->subMonths(3)->startOfDay()->format('Y-m-d')),
                    'to' => $request->date_to ?? ($hasExplicitFilter ? 'All time' : now()->format('Y-m-d')),
                ],
                'group_by' => $request->group_by,
                'user_scope' => $user->hasAnyRole(['Admin', 'Super Admin']) ? 'all_data' : ($user->hasRole('Kurir') ? 'assigned_shipments' : 'own_shipments'),
                'categorization_logic' => [
                    'internal' => 'Shipment dengan assigned_driver_id (via bulk-assign-driver)',
                    'online' => 'Shipment dengan vehicle_used & shipping_cost (via complete-shipments)',
                ],
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    private function applyDateFilterForReport($query, $request): void
    {
        if ($request->date_from && $request->date_to) {
            $from = \Carbon\Carbon::parse($request->date_from)->startOfDay();
            $to   = \Carbon\Carbon::parse($request->date_to)->endOfDay();
            $query->whereBetween('completed_at', [$from, $to]);
        } elseif ($request->period) {
            switch ($request->period) {
                case 'today':
                    $query->whereDate('completed_at', now()->format('Y-m-d'));
                    break;
                case 'week':
                    $query->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()]);
                    break;
                case 'quarter':
                    $query->whereBetween('completed_at', [now()->startOfQuarter(), now()->endOfQuarter()]);
                    break;
                case 'year':
                    $query->whereBetween('completed_at', [now()->startOfYear(), now()->endOfYear()]);
                    break;
            }
        }
    }

    /**
     * ✅ KATEGORISASI BARU: Internal Services
     * Shipment yang di-assign melalui bulk-assign-driver endpoint
     * Ciri: memiliki assigned_driver_id
     */
    private function categorizeInternalServicesNew($shipments)
    {
        return $shipments->filter(function ($shipment) {
            // Internal = ada assigned_driver_id (dari bulk-assign-driver)
            return !is_null($shipment->assigned_driver_id);
        });
    }

    /**
     * ✅ KATEGORISASI BARU: Online Services  
     * Shipment yang di-complete melalui complete-shipments endpoint
     * Ciri: memiliki vehicle_used dan shipping_cost
     */
    private function categorizeOnlineServicesNew($shipments)
    {
        return $shipments->filter(function ($shipment) {
            // Online = ada vehicle_used dan shipping_cost (dari complete-shipments)
            return !is_null($shipment->vehicle_used) && !is_null($shipment->shipping_cost);
        });
    }

    /**
     * ✅ TIME BREAKDOWN BARU dengan logika kategorisasi yang tepat
     */
    private function getTimeBreakdownForServicesNew($internalServices, $onlineServices, $groupBy)
    {
        $breakdown = [];

        switch ($groupBy) {
            case 'day':
                $format = 'Y-m-d';
                $labelFormat = 'd M Y';
                break;
            case 'week':
                $format = 'Y-W';
                $labelFormat = 'W, Y';
                break;
            case 'month':
                $format = 'Y-m';
                $labelFormat = 'M Y';
                break;
            case 'year':
                $format = 'Y';
                $labelFormat = 'Y';
                break;
            default:
                $format = 'Y-m-d';
                $labelFormat = 'd M Y';
        }

        // Group internal services
        $internalGrouped = $internalServices->groupBy(function ($item) use ($format) {
            return Carbon::parse($item->completed_at)->format($format);
        });

        // Group online services
        $onlineGrouped = $onlineServices->groupBy(function ($item) use ($format) {
            return Carbon::parse($item->completed_at)->format($format);
        });

        // Combine periods
        $allPeriods = collect($internalGrouped->keys())
            ->merge($onlineGrouped->keys())
            ->unique()
            ->sort();

        foreach ($allPeriods as $period) {
            $internalCount = $internalGrouped->get($period, collect())->count();
            $onlineCount = $onlineGrouped->get($period, collect())->count();
            $internalCost = $internalGrouped->get($period, collect())->sum('shipping_cost') ?? 0;
            $onlineCost = $onlineGrouped->get($period, collect())->sum('shipping_cost') ?? 0;

            $breakdown[] = [
                'period' => $period,
                'period_label' => Carbon::createFromFormat($format, $period)->format($labelFormat),
                'internal_courier' => [
                    'count' => $internalCount,
                    'cost' => $internalCost,
                ],
                'online_courier' => [
                    'count' => $onlineCount,
                    'cost' => $onlineCost,
                ],
                'total' => [
                    'count' => $internalCount + $onlineCount,
                    'cost' => $internalCost + $onlineCost,
                ],
            ];
        }

        return $breakdown;
    }

    /**
     * ✅ VEHICLE ANALYSIS BARU dengan logika kategorisasi yang tepat
     */
    private function getVehicleAnalysisNew($shipments)
    {
        $vehicleStats = $shipments->groupBy(function ($shipment) {
            // Untuk internal: tampilkan info driver + vehicle type jika ada
            if (!is_null($shipment->assigned_driver_id)) {
                $driverName = $shipment->driver?->name ?? 'Unknown Driver';
                $vehicleInfo = $shipment->vehicle_used ?? 'Internal Vehicle';
                return "Internal - {$driverName} ({$vehicleInfo})";
            }
            
            // Untuk online: tampilkan vehicle_used
            if (!is_null($shipment->vehicle_used)) {
                return "Online - {$shipment->vehicle_used}";
            }
            
            return 'Unknown Service';
        })
        ->map(function ($group, $vehicle) {
            $count = $group->count();
            $totalCost = $group->sum('shipping_cost') ?? 0;
            $hasDriver = $group->first()->assigned_driver_id ? true : false;
            
            return [
                'vehicle' => $vehicle,
                'count' => $count,
                'total_cost' => $totalCost,
                'average_cost' => $count > 0 ? round($totalCost / $count, 2) : 0,
                'service_type' => $hasDriver ? 'Internal Courier' : 'Online Courier',
            ];
        })
        ->sortByDesc('count')
        ->values()
        ->take(20); // Top 20 vehicles

        return $vehicleStats->toArray();
    }

    /**
     * ✅ DETAILED SERVICE BREAKDOWN BARU dengan logika kategorisasi yang tepat
     */
    private function getDetailedServiceBreakdownNew($services, $serviceType)
    {
        if ($serviceType === 'internal') {
            // Group by driver untuk internal services
            return $services->groupBy('assigned_driver_id')
                ->map(function ($group, $driverId) {
                    $count = $group->count();
                    $totalCost = $group->sum('shipping_cost') ?? 0;
                    $driverName = $group->first()->driver?->name ?? 'Unknown Driver';
                    $vehicleInfo = $group->first()->vehicle_used ?? 'Internal Vehicle';
                    
                    return [
                        'service_name' => "Internal - {$driverName}",
                        'vehicle_info' => $vehicleInfo,
                        'driver_id' => $driverId,
                        'driver_name' => $driverName,
                        'count' => $count,
                        'total_cost' => $totalCost,
                        'average_cost' => $count > 0 ? round($totalCost / $count, 2) : 0,
                        'shipments' => $group->map(function ($shipment) {
                            return [
                                'shipment_id' => $shipment->shipment_id,
                                'cost' => $shipment->shipping_cost ?? 0,
                                'driver' => $shipment->driver?->name ?? 'Unknown Driver',
                                'completed_at' => $shipment->completed_at?->format('Y-m-d H:i:s'),
                                'completed_by' => $shipment->completedBy?->name ?? 'Unknown Admin',
                            ];
                        })->toArray(),
                    ];
                })
                ->sortByDesc('count')
                ->values()
                ->toArray();
        } else {
            // Group by vehicle_used untuk online services
            return $services->groupBy('vehicle_used')
                ->map(function ($group, $vehicle) {
                    $count = $group->count();
                    $totalCost = $group->sum('shipping_cost') ?? 0;
                    
                    return [
                        'service_name' => "Online - {$vehicle}",
                        'vehicle_info' => $vehicle,
                        'count' => $count,
                        'total_cost' => $totalCost,
                        'average_cost' => $count > 0 ? round($totalCost / $count, 2) : 0,
                        'shipments' => $group->map(function ($shipment) {
                            return [
                                'shipment_id' => $shipment->shipment_id,
                                'cost' => $shipment->shipping_cost ?? 0,
                                'vehicle_used' => $shipment->vehicle_used,
                                'completed_at' => $shipment->completed_at?->format('Y-m-d H:i:s'),
                                'completed_by' => $shipment->completedBy?->name ?? 'Unknown Admin',
                            ];
                        })->toArray(),
                    ];
                })
                ->sortByDesc('count')
                ->values()
                ->toArray();
        }
    }

    /**
     * GET /api/v1/dashboard/user-request-analysis
     * Analisa request pengiriman: tujuan tersering, pembuat tiket/divisi terbanyak,
     * dan jumlah per prioritas. Semua SQL GROUP BY aggregates (no row hydration),
     * scoped to a date range so this stays cheap regardless of table size.
     *
     * Query params:
     *   date_from  (default: 3 bulan lalu)
     *   date_to    (default: hari ini)
     */
    public function getUserRequestAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->date_from
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->subMonths(3)->startOfDay();
        $dateTo = $request->date_to
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $shipmentsInRange = fn () => DB::table('shipments as s')
            ->whereBetween('s.created_at', [$dateFrom, $dateTo]);

        // Tempat tujuan tersering — prefer the linked customer's company name
        // (consistent even if free-text receiver_company has spelling variants),
        // falling back to the raw receiver_company for destinations not linked to
        // a saved customer record.
        $topDestinations = DB::table('shipment_destinations as sd')
            ->join('shipments as s', 's.id', '=', 'sd.shipment_id')
            ->leftJoin('customers as c', 'sd.customer_id', '=', 'c.id')
            ->whereBetween('s.created_at', [$dateFrom, $dateTo])
            ->selectRaw('COALESCE(c.company_name, sd.receiver_company) as place_name')
            ->selectRaw('count(*) as total')
            ->groupBy('place_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // User/divisi pembuat tiket terbanyak — division shown is the one tagged
        // directly on the shipment (shipments.division_id), not the creator's home
        // division, since that's the more relevant "which division requested this."
        $topCreators = $shipmentsInRange()
            ->join('users as u', 's.created_by', '=', 'u.id')
            ->leftJoin('divisions as d', 's.division_id', '=', 'd.id')
            ->select('u.id as user_id', 'u.name as user_name')
            ->selectRaw('COALESCE(d.name, "Tanpa Divisi") as division_name')
            ->selectRaw('count(*) as total')
            ->groupBy('u.id', 'u.name', 'd.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $divisionCounts = $shipmentsInRange()
            ->leftJoin('divisions as d', 's.division_id', '=', 'd.id')
            ->selectRaw('COALESCE(d.name, "Tanpa Divisi") as division_name')
            ->selectRaw('count(*) as total')
            ->groupBy('d.name')
            ->orderByDesc('total')
            ->get();

        $priorityCounts = $shipmentsInRange()
            ->select('s.priority')
            ->selectRaw('count(*) as total')
            ->groupBy('s.priority')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'data' => [
                'top_destinations' => $topDestinations,
                'top_creators' => $topCreators,
                'division_counts' => $divisionCounts,
                'priority_counts' => $priorityCounts,
            ],
            'meta' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * GET /api/v1/dashboard/cancel-order-analysis
     * Analisa pengiriman yang dibatalkan: total & tren cancel, breakdown alasan
     * cancel (normalized case/whitespace so free-text variants group together),
     * dan siapa yang membatalkan. Filtered by cancelled_at (when the cancellation
     * happened), not created_at — a shipment created long ago but cancelled today
     * should count toward today's range. All SQL GROUP BY aggregates.
     *
     * Query params:
     *   date_from  (default: 3 bulan lalu)
     *   date_to    (default: hari ini)
     */
    public function getCancelOrderAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->date_from
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->subMonths(3)->startOfDay();
        $dateTo = $request->date_to
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $cancelledInRange = fn () => DB::table('shipments as s')
            ->where('s.status', 'cancelled')
            ->whereBetween('s.cancelled_at', [$dateFrom, $dateTo]);

        $totalCancelled = $cancelledInRange()->count();
        $totalShipments = DB::table('shipments as s')
            ->whereBetween('s.created_at', [$dateFrom, $dateTo])
            ->count();

        // Alasan cancel — normalize case/whitespace so "Barang rusak" and
        // "barang  rusak " group together instead of showing as separate rows.
        $cancelReasons = $cancelledInRange()
            ->selectRaw("COALESCE(NULLIF(TRIM(LOWER(s.cancel_reason)), ''), 'tidak ada alasan') as reason_key")
            ->selectRaw('MIN(TRIM(s.cancel_reason)) as reason_label')
            ->selectRaw('count(*) as total')
            ->groupBy('reason_key')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->reason_label = $row->reason_label !== null && $row->reason_label !== '' ? $row->reason_label : 'Tidak ada alasan';
                return $row;
            });

        // Siapa yang membatalkan.
        $cancelledByUsers = $cancelledInRange()
            ->join('users as u', 's.cancelled_by', '=', 'u.id')
            ->leftJoin('divisions as d', 'u.division_id', '=', 'd.id')
            ->select('u.id as user_id', 'u.name as user_name')
            ->selectRaw('COALESCE(d.name, "Tanpa Divisi") as division_name')
            ->selectRaw('count(*) as total')
            ->groupBy('u.id', 'u.name', 'd.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Cancel tanpa cancelled_by tercatat (mis. dibatalkan lewat proses lama /
        // data historis) — dihitung terpisah supaya tidak hilang dari total.
        $cancelledByUnknownCount = $cancelledInRange()->whereNull('s.cancelled_by')->count();

        return response()->json([
            'data' => [
                'total_cancelled' => $totalCancelled,
                'total_shipments' => $totalShipments,
                'cancellation_rate' => $totalShipments > 0 ? round(($totalCancelled / $totalShipments) * 100, 2) : 0,
                'cancel_reasons' => $cancelReasons,
                'cancelled_by_users' => $cancelledByUsers,
                'cancelled_by_unknown' => $cancelledByUnknownCount,
            ],
            'meta' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * GET /api/v1/dashboard/delivery-trend
     * Tren pengiriman per hari: Kurir Internal vs Kurir Online
     *
     * - Internal : assigned_driver_id IS NOT NULL  (driver internal assigned)
     * - Online   : vehicle_used IS NOT NULL AND shipping_cost IS NOT NULL
     *
     * Query params:
     *   date_from  (default: 3 bulan lalu)
     *   date_to    (default: hari ini)
     */
    public function getDeliveryTrend(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $dateFrom = Carbon::parse($request->input('date_from', Carbon::now()->subMonths(3)->format('Y-m-d')))->startOfDay();
        $dateTo   = Carbon::parse($request->input('date_to',   Carbon::now()->format('Y-m-d')))->endOfDay();

        // Base query — semua status kecuali cancelled
        $query = Shipment::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', '!=', 'cancelled')
            ->select(['id', 'assigned_driver_id', 'vehicle_used', 'shipping_cost', 'created_at']);

        // Role-based scope
        $user = $request->user();
        if ($user->hasRole('Kurir')) {
            $query->where('assigned_driver_id', $user->id);
        } elseif (! $user->hasAnyRole(['Admin', 'Super Admin'])) {
            $query->where('created_by', $user->id);
        }

        $shipments = $query->get();

        // Kelompokkan per hari
        $grouped = $shipments->groupBy(function ($s) {
            return Carbon::parse($s->created_at)->format('Y-m-d');
        });

        // Isi setiap hari dalam range (termasuk hari tanpa data = 0)
        $result  = [];
        $current = $dateFrom->copy()->startOfDay();
        $end     = $dateTo->copy()->startOfDay();

        while ($current <= $end) {
            $key   = $current->format('Y-m-d');
            $items = $grouped->get($key, collect());

            $internal = $items->filter(fn($s) => ! is_null($s->assigned_driver_id))->count();
            $online   = $items->filter(fn($s) => ! empty($s->vehicle_used) && ! is_null($s->shipping_cost))->count();

            $result[] = [
                'period'   => $current->locale('id')->isoFormat('D MMM'),
                'period_key' => $key,
                'online'   => $online,
                'internal' => $internal,
                'total'    => $online + $internal,
            ];

            $current->addDay();
        }

        $totalOnline   = $shipments->filter(fn($s) => ! empty($s->vehicle_used) && ! is_null($s->shipping_cost))->count();
        $totalInternal = $shipments->filter(fn($s) => ! is_null($s->assigned_driver_id))->count();

        return response()->json([
            'data' => $result,
            'meta' => [
                'date_from'      => $dateFrom->format('Y-m-d'),
                'date_to'        => $dateTo->format('Y-m-d'),
                'total_online'   => $totalOnline,
                'total_internal' => $totalInternal,
                'total'          => $totalOnline + $totalInternal,
            ],
        ]);
    }

    /**
     * GET /dashboard/driver-accumulation
     * Statistik pengiriman per driver, digroup by status shipment + takeover.
     * Params: date_from, date_to (default: 3 bulan terakhir)
     */
    public function getDriverAccumulation(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $dateFrom = Carbon::parse($request->input('date_from', Carbon::now()->subMonths(3)->startOfMonth()->format('Y-m-d')))->startOfDay();
        $dateTo   = Carbon::parse($request->input('date_to', Carbon::now()->format('Y-m-d')))->endOfDay();

        // Ambil semua driver aktif
        $drivers = User::role('Kurir')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $driverIds = $drivers->pluck('id');

        // Single query for every driver's progress rows in range, grouped in-memory
        // per driver — replaces what used to be up to 7 queries per driver.
        $progressByDriver = ShipmentProgress::whereIn('driver_id', $driverIds)
            ->whereBetween('progress_time', [$dateFrom, $dateTo])
            ->get(['driver_id', 'destination_id', 'status', 'progress_time'])
            ->groupBy('driver_id');

        // Single query for "assigned but not yet started" counts across all drivers.
        $assignedCounts = Shipment::whereIn('assigned_driver_id', $driverIds)
            ->where('status', 'assigned')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('assigned_driver_id')
            ->selectRaw('count(*) as total')
            ->groupBy('assigned_driver_id')
            ->pluck('total', 'assigned_driver_id');

        $statusKeys = ['picked', 'in_progress', 'arrived', 'delivered', 'takeover'];

        $result = $drivers->map(function (User $driver) use ($progressByDriver, $assignedCounts, $statusKeys) {
            $rows = $progressByDriver->get($driver->id, collect());
            $byStatus = $rows->groupBy('status');

            $pickedRows = $byStatus->get('picked', collect())->keyBy('destination_id');
            $deliveredRows = $byStatus->get('delivered', collect())->keyBy('destination_id');

            // Hitung total durasi: dari status 'picked' ke 'delivered' per destinasi
            $totalMinutes  = 0;
            $countFinished = 0;
            foreach ($pickedRows as $destId => $picked) {
                if (isset($deliveredRows[$destId])) {
                    $totalMinutes += abs($picked->progress_time->diffInMinutes($deliveredRows[$destId]->progress_time));
                    $countFinished++;
                }
            }

            $avgMinutes = $countFinished > 0 ? (int) round($totalMinutes / $countFinished) : 0;

            $statusCounts = [];
            foreach ($statusKeys as $status) {
                $statusCounts[$status] = $byStatus->get($status, collect())->count();
            }

            return [
                'driver'                 => ['id' => $driver->id, 'name' => $driver->name],
                'assigned'               => $assignedCounts->get($driver->id, 0),
                'picked'                 => $statusCounts['picked'],
                'in_progress'            => $statusCounts['in_progress'],
                'arrived'                => $statusCounts['arrived'],
                'delivered'              => $statusCounts['delivered'],
                'takeover'               => $statusCounts['takeover'],
                'total_duration_minutes' => $totalMinutes,
                'avg_duration_minutes'   => $avgMinutes,
                'deliveries_counted'     => $countFinished,
            ];
        })->values();

        return response()->json([
            'data' => $result,
            'meta' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to'   => $dateTo->format('Y-m-d'),
            ],
        ]);
    }

    public function dashboardDelivery(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Kurir')) {
            return response()->json(['message' => 'Endpoint ini hanya untuk kurir'], 403);
        }

        $idleResponse = [
            'data' => [
                'mode' => 'idle',
                'message' => 'Belum tersedia',
                'subtitle' => 'Bersiap mengirim kiriman',
                'bulk_assignment_id' => null,
                'total_shipments' => 0,
                'total_steps' => 0,
                'completed_steps' => 0,
                'progress_percent' => 0,
                'shipments' => [],
            ],
        ];

        $allBulks = DB::table('bulk_assignments')
            ->where('driver_id', $user->id)
            ->orderByDesc('assigned_at')
            ->get();

        if ($allBulks->isEmpty()) {
            return response()->json($idleResponse);
        }

        // Fetch every shipment id referenced by this driver's bulk assignments in one
        // query, then pick the active bulk in-memory — avoids an exists() query per
        // bulk assignment (previously up to 2 queries per row of $allBulks).
        $bulkShipmentIds = [];
        foreach ($allBulks as $bulk) {
            $bulkShipmentIds[$bulk->id] = json_decode($bulk->shipment_ids, true) ?? [];
        }

        $allReferencedIds = array_unique(array_merge(...array_values($bulkShipmentIds ?: [[]])));

        $statusByShipmentId = Shipment::whereIn('id', $allReferencedIds)
            ->where('assigned_driver_id', $user->id)
            ->pluck('status', 'id');

        $activeBulk = null;

        foreach ($allBulks as $bulk) {
            $shipmentIds = $bulkShipmentIds[$bulk->id];
            if (empty($shipmentIds)) continue;

            $hasInProgress = collect($shipmentIds)->contains(fn ($id) => ($statusByShipmentId[$id] ?? null) === 'in_progress');

            if ($hasInProgress) {
                $activeBulk = $bulk;
                break;
            }
        }

        if (!$activeBulk) {
            foreach ($allBulks as $bulk) {
                $shipmentIds = $bulkShipmentIds[$bulk->id];
                if (empty($shipmentIds)) continue;

                $hasAssigned = collect($shipmentIds)->contains(fn ($id) => ($statusByShipmentId[$id] ?? null) === 'assigned');

                if ($hasAssigned) {
                    $activeBulk = $bulk;
                    break;
                }
            }
        }

        if (!$activeBulk) {
            $latestBulk = $allBulks->first();
            $shipmentIds = json_decode($latestBulk->shipment_ids, true) ?? [];

            $allDone = Shipment::whereIn('id', $shipmentIds)
                ->where('assigned_driver_id', $user->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->doesntExist();

            if ($allDone) {
                $activeBulk = $latestBulk;
            }
        }

        if (!$activeBulk) {
            return response()->json($idleResponse);
        }

        $shipmentIds = json_decode($activeBulk->shipment_ids, true) ?? [];

        $shipments = Shipment::with(['destinations' => fn($q) => $q->orderBy('sequence_order')])
            ->whereIn('id', $shipmentIds)
            ->where('assigned_driver_id', $user->id)
            ->get();

        $orderedShipments = collect();
        foreach ($shipmentIds as $id) {
            $s = $shipments->firstWhere('id', $id);
            if ($s) $orderedShipments->push($s);
        }

        if ($orderedShipments->isEmpty()) {
            return response()->json($idleResponse);
        }

        $shipmentsData = [];
        $totalSteps = 0;
        $completedSteps = 0;

        foreach ($orderedShipments as $shipment) {
            foreach ($shipment->destinations as $dest) {
                $totalSteps += 2;

                $dikirimDone = in_array($dest->status, ['in_progress', 'arrived', 'delivered', 'returning', 'finished']);
                $diterimaDone = in_array($dest->status, ['delivered', 'returning', 'finished']);

                if ($dikirimDone) $completedSteps++;
                if ($diterimaDone) $completedSteps++;

                $shipmentsData[] = [
                    'shipment_id' => $shipment->shipment_id,
                    'destination_id' => $dest->id,
                    'receiver_name' => $dest->receiver_name,
                    'delivery_address' => $dest->delivery_address,
                    'sequence_order' => $dest->sequence_order,
                    'status' => $dest->status,
                    'dikirim' => $dikirimDone,
                    'diterima' => $diterimaDone,
                ];
            }
        }

        $progressPercent = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0;

        $allCompleted = $orderedShipments->every(fn($s) => in_array($s->status, ['completed', 'cancelled']));
        $anyInProgress = $orderedShipments->contains(fn($s) => $s->status === 'in_progress');

        // Check if any destination has returning status (kurir sedang pulang)
        $hasReturning = false;
        $allFinishedOrCompleted = true;
        foreach ($orderedShipments as $shipment) {
            foreach ($shipment->destinations as $dest) {
                if ($dest->status === 'returning') {
                    $hasReturning = true;
                }
                if (!in_array($dest->status, ['finished', 'delivered', 'returning'])) {
                    $allFinishedOrCompleted = false;
                }
            }
        }

        // Find the first destination not yet fully received (diterima)
        // This walks in bulk order: shipment 1 dest 1, shipment 1 dest 2, shipment 2 dest 1, etc.
        $currentShipmentNumber = 0;
        $currentShipmentSPJ = null;
        foreach ($orderedShipments->values() as $idx => $shipment) {
            $hasUnfinishedDest = false;
            foreach ($shipment->destinations as $dest) {
                $diterimaDone = in_array($dest->status, ['delivered', 'returning', 'finished']);
                if (!$diterimaDone) {
                    $hasUnfinishedDest = true;
                    break;
                }
            }
            if ($hasUnfinishedDest) {
                $currentShipmentNumber = $idx + 1;
                $currentShipmentSPJ = $shipment->shipment_id;
                break;
            }
        }

        if ($allCompleted) {
            $mode = 'completed';
            $currentShipmentNumber = $orderedShipments->count();
            $currentShipmentSPJ = $orderedShipments->last()?->shipment_id;
        } elseif ($hasReturning && $allFinishedOrCompleted) {
            $mode = 'returning';
            $currentShipmentNumber = $orderedShipments->count();
            $currentShipmentSPJ = null;
        } elseif ($anyInProgress) {
            $mode = 'delivering';
        } else {
            $mode = 'ready';
            $currentShipmentNumber = 0;
            $currentShipmentSPJ = null;
        }

        return response()->json([
            'data' => [
                'mode' => $mode,
                'bulk_assignment_id' => $activeBulk->id,
                'total_shipments' => $orderedShipments->count(),
                'current_shipment_number' => $currentShipmentNumber,
                'current_shipment_spj' => $currentShipmentSPJ,
                'total_steps' => $totalSteps,
                'completed_steps' => $completedSteps,
                'progress_percent' => $progressPercent,
                'shipments' => $shipmentsData,
            ],
        ]);
    }
}
