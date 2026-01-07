<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $user = $request->user();

        // ✅ PRIVATE DASHBOARD: Setiap user melihat data mereka sendiri
        $stats = [
            'shipments' => $this->getPrivateShipmentStats($user),
            'deliveries' => $this->getPrivateDeliveryStats($user),
            'performance' => $this->getPrivatePerformanceStats($user),
        ];

        // Role-specific data - tetap private untuk masing-masing user
        if ($user->hasRole('Admin')) {
            $stats['admin'] = $this->getAdminStats();
        } elseif ($user->hasRole('Kurir')) {
            $stats['driver'] = $this->getDriverStats($user);
        } else {
            $stats['user'] = $this->getUserStats($user);
        }

        return response()->json([
            'data' => $stats,
        ]);
    }

    private function getPrivateShipmentStats($user): array
    {
        // ✅ PRIVATE DASHBOARD: Data berbeda berdasarkan role user
        if ($user->hasRole('Admin')) {
            // Admin melihat semua shipment
            $query = Shipment::query();
        } elseif ($user->hasRole('Kurir')) {
            // Kurir hanya melihat shipment yang assigned ke mereka
            $query = Shipment::where('assigned_driver_id', $user->id);
        } else {
            // User biasa hanya melihat shipment yang mereka buat
            $query = Shipment::where('created_by', $user->id);
        }

        return [
            'total' => $query->count(),
            'pending' => $query->clone()->where('status', 'pending')->count(),
            'approved' => $query->clone()->where('status', 'approved')->count(),
            'assigned' => $query->clone()->where('status', 'assigned')->count(),
            'in_progress' => $query->clone()->where('status', 'in_progress')->count(),
            'completed' => $query->clone()->where('status', 'completed')->count(),
            'cancelled' => $query->clone()->where('status', 'cancelled')->count(),
            'urgent' => $query->clone()->where('priority', 'urgent')->count(),
        ];
    }

    private function getPrivateDeliveryStats($user): array
    {
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        // ✅ PRIVATE DASHBOARD: Data delivery berbeda berdasarkan role user
        if ($user->hasRole('Admin')) {
            // Admin melihat semua delivery
            $query = Shipment::query();
        } elseif ($user->hasRole('Kurir')) {
            // Kurir hanya melihat delivery yang mereka handle
            $query = Shipment::where('assigned_driver_id', $user->id);
        } else {
            // User biasa hanya melihat delivery shipment mereka
            $query = Shipment::where('created_by', $user->id);
        }

        return [
            'today' => $query->clone()->where('status', 'completed')
                ->whereDate('updated_at', $today)->count(),
            'this_week' => $query->clone()->where('status', 'completed')
                ->where('updated_at', '>=', $thisWeek)->count(),
            'this_month' => $query->clone()->where('status', 'completed')
                ->where('updated_at', '>=', $thisMonth)->count(),
        ];
    }

    private function getPrivatePerformanceStats($user): array
    {
        $thisMonth = now()->startOfMonth();

        // ✅ PRIVATE DASHBOARD: Data performa berbeda berdasarkan role user
        if ($user->hasRole('Admin')) {
            // Admin melihat performa keseluruhan sistem
            $query = Shipment::query()->where('updated_at', '>=', $thisMonth);
        } elseif ($user->hasRole('Kurir')) {
            // Kurir melihat performa mereka sendiri
            $query = Shipment::where('assigned_driver_id', $user->id)
                ->where('updated_at', '>=', $thisMonth);
        } else {
            // User biasa melihat performa shipment mereka
            $query = Shipment::where('created_by', $user->id)
                ->where('updated_at', '>=', $thisMonth);
        }

        $total = $query->count();
        $completed = $query->clone()->where('status', 'completed')->count();
        $onTime = $query->clone()->where('status', 'completed')
            ->whereRaw('updated_at <= deadline')->count();

        return [
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'on_time_rate' => $completed > 0 ? round(($onTime / $completed) * 100, 2) : 0,
        ];
    }

    private function getAdminStats(): array
    {
        return [
            'pending_approvals' => Shipment::where('status', 'pending')->count(),
            'unassigned_shipments' => Shipment::where('status', 'approved')
                ->whereNull('assigned_driver_id')->count(),
            'active_drivers' => User::role('Kurir')->where('is_active', true)->count(),
            'total_users' => User::where('is_active', true)->count(),
            'recent_shipments' => Shipment::with(['creator', 'driver'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'shipment_id', 'status', 'priority', 'created_by', 'assigned_driver_id', 'created_at']),
        ];
    }

    private function getDriverStats($user): array
    {
        $today = now()->startOfDay();

        return [
            'assigned_today' => Shipment::where('assigned_driver_id', $user->id)
                ->whereDate('created_at', $today)->count(),
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
        ];
    }

    private function getUserStats($user): array
    {
        $thisMonth = now()->startOfMonth();

        return [
            'my_shipments' => Shipment::where('created_by', $user->id)->count(),
            'pending_approval' => Shipment::where('created_by', $user->id)
                ->where('status', 'pending')->count(),
            'in_delivery' => Shipment::where('created_by', $user->id)
                ->whereIn('status', ['assigned', 'in_progress'])->count(),
            'completed_this_month' => Shipment::where('created_by', $user->id)
                ->where('status', 'completed')
                ->where('created_at', '>=', $thisMonth)->count(),
        ];
    }

    public function getChartData(Request $request): JsonResponse
    {
        $period = $request->get('period', 'week'); // week, month, year
        $user = $request->user();

        $data = [];

        switch ($period) {
            case 'week':
                $data = $this->getWeeklyChartData($user);
                break;
            case 'month':
                $data = $this->getMonthlyChartData($user);
                break;
            case 'year':
                $data = $this->getYearlyChartData($user);
                break;
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    private function getWeeklyChartData($user): array
    {
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();

        // ✅ PRIVATE DASHBOARD: Chart data berbeda berdasarkan role user
        if ($user->hasRole('Admin')) {
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
        if ($user->hasRole('Admin')) {
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
        if ($user->hasRole('Admin')) {
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
            'chart_type' => 'required|in:daily,monthly,yearly,category,vehicle_type,customer,status,priority,total',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'period' => 'nullable|in:day,week,month,year',
            'category_id' => 'nullable|exists:shipment_categories,id',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
        ]);

        $user = $request->user();
        $chartType = $request->chart_type;

        // ✅ PRIVATE DASHBOARD: Base query berbeda berdasarkan role user
        $query = Shipment::with(['category', 'vehicleType', 'creator.division']);
        
        if ($user->hasRole('Admin')) {
            // Admin melihat semua data
            // Query tetap tanpa filter
        } elseif ($user->hasRole('Kurir')) {
            // Kurir hanya melihat shipment yang assigned ke mereka
            $query->where('assigned_driver_id', $user->id);
        } else {
            // User biasa hanya melihat shipment yang mereka buat
            $query->where('created_by', $user->id);
        }

        // Date filtering
        $this->applyDateFilter($query, $request);

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

        return response()->json([
            'data' => $data,
            'meta' => [
                'chart_type' => $chartType,
                'period' => $request->period ?? 'custom',
                'date_range' => [
                    'from' => $request->date_from ?? 'All time',
                    'to' => $request->date_to ?? 'All time',
                ],
                'filters_applied' => [
                    'category_id' => $request->category_id,
                    'vehicle_type_id' => $request->vehicle_type_id,
                ],
                'user_scope' => $user->hasRole('Admin') ? 'all_data' : ($user->hasRole('Kurir') ? 'assigned_shipments' : 'own_shipments'),
            ],
        ]);
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
                'per_page' => 'nullable|integer|min:1|max:100',
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
                'destinations:id,shipment_id,delivery_address,receiver_name,status'
            ]);

            // ✅ AUTO-HIDE COMPLETED: Secara default sembunyikan tiket completed
            if (!$request->get('show_completed', false)) {
                $query->where('status', '!=', 'completed');
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
            $perPage = $request->get('per_page', 10);
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

            // Summary statistics untuk antrian (exclude completed by default)
            $showCompleted = $request->get('show_completed', false);
            $summaryQuery = Shipment::query();
            if (!$showCompleted) {
                $summaryQuery->where('status', '!=', 'completed');
            }
            
            $summary = [
                'total_shipments' => $shipments->total(),
                'total_in_system' => Shipment::count(), // Total semua tiket di sistem
                'status_counts' => [
                    'pending' => $summaryQuery->clone()->where('status', 'pending')->count(),
                    'assigned' => $summaryQuery->clone()->where('status', 'assigned')->count(),
                    'in_progress' => $summaryQuery->clone()->where('status', 'in_progress')->count(),
                    'completed' => Shipment::where('status', 'completed')->count(), // Selalu tampilkan jumlah completed
                    'cancelled' => $summaryQuery->clone()->where('status', 'cancelled')->count(),
                ],
                'priority_counts' => [
                    'urgent' => $summaryQuery->clone()->where('priority', 'urgent')->count(),
                    'regular' => $summaryQuery->clone()->where('priority', 'regular')->count(),
                ],
                'overdue_count' => $summaryQuery->clone()
                    ->where('deadline', '<', now())
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->count(),
                'completed_hidden' => !$showCompleted,
                'completed_count' => Shipment::where('status', 'completed')->count(),
            ];

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
}
