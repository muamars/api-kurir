<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Base statistics
        $stats = [
            'shipments' => $this->getShipmentStats($user),
            'deliveries' => $this->getDeliveryStats($user),
            'performance' => $this->getPerformanceStats($user),
        ];

        // Role-specific data
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

    private function getShipmentStats($user): array
    {
        $query = Shipment::query();

        // Filter by user role
        if (! $user->hasRole('Admin')) {
            if ($user->hasRole('Kurir')) {
                $query->where('assigned_driver_id', $user->id);
            } else {
                $query->where('created_by', $user->id);
            }
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

    private function getDeliveryStats($user): array
    {
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        $query = Shipment::query();

        if (! $user->hasRole('Admin')) {
            if ($user->hasRole('Kurir')) {
                $query->where('assigned_driver_id', $user->id);
            } else {
                $query->where('created_by', $user->id);
            }
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

    private function getPerformanceStats($user): array
    {
        $thisMonth = now()->startOfMonth();

        $query = Shipment::query()->where('updated_at', '>=', $thisMonth);

        if (! $user->hasRole('Admin')) {
            if ($user->hasRole('Kurir')) {
                $query->where('assigned_driver_id', $user->id);
            } else {
                $query->where('created_by', $user->id);
            }
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

        $query = Shipment::whereBetween('created_at', [$startDate, $endDate]);

        if (! $user->hasRole('Admin')) {
            if ($user->hasRole('Kurir')) {
                $query->where('assigned_driver_id', $user->id);
            } else {
                $query->where('created_by', $user->id);
            }
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

        $query = Shipment::whereBetween('created_at', [$startDate, $endDate]);

        if (! $user->hasRole('Admin')) {
            if ($user->hasRole('Kurir')) {
                $query->where('assigned_driver_id', $user->id);
            } else {
                $query->where('created_by', $user->id);
            }
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

        $query = Shipment::whereBetween('created_at', [$startDate, $endDate]);

        if (! $user->hasRole('Admin')) {
            if ($user->hasRole('Kurir')) {
                $query->where('assigned_driver_id', $user->id);
            } else {
                $query->where('created_by', $user->id);
            }
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

        // Base query
        $query = Shipment::with(['category', 'vehicleType', 'creator.division']);

        // Role-based filtering
        if (!$user->hasRole('Admin')) {
            if ($user->hasRole('Kurir')) {
                $query->where('assigned_driver_id', $user->id);
            } else {
                $query->where('created_by', $user->id);
            }
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
            'created' => 'Dibuat',
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
                'pending' => ($statusBreakdown->get('created')?->count ?? 0) + ($statusBreakdown->get('pending')?->count ?? 0) + ($statusBreakdown->get('assigned')?->count ?? 0),
                'cancelled' => $statusBreakdown->get('cancelled')?->count ?? 0,
                'urgent' => $priorityBreakdown->get('urgent')?->count ?? 0,
                'regular' => $priorityBreakdown->get('regular')?->count ?? 0,
            ],
            'status_breakdown' => [
                'created' => [
                    'count' => $statusBreakdown->get('created')?->count ?? 0,
                    'label' => 'Dibuat',
                    'percentage' => $totalShipments > 0 ? round((($statusBreakdown->get('created')?->count ?? 0) / $totalShipments) * 100, 2) : 0,
                ],
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
     * Get all shipments table with pagination for dashboard
     */
    public function getShipmentsTable(Request $request): JsonResponse
    {
        try {
            // Test basic functionality first
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            // Simple query without relationships first
            $query = Shipment::query();

            // Role-based filtering
            if (!$user->hasRole('Admin')) {
                if ($user->hasRole('Kurir')) {
                    $query->where('assigned_driver_id', $user->id);
                } else {
                    $query->where('created_by', $user->id);
                }
            }

            // Get simple paginated results
            $shipments = $query->paginate(10);

            // Simple data transformation
            $tableData = $shipments->getCollection()->map(function ($shipment) {
                return [
                    'id' => $shipment->id,
                    'shipment_id' => $shipment->shipment_id ?? 'N/A',
                    'status' => $shipment->status ?? 'unknown',
                    'priority' => $shipment->priority ?? 'regular',
                    'pickup_address' => $shipment->pickup_address ?? 'N/A',
                    'created_at' => $shipment->created_at ? $shipment->created_at->format('Y-m-d H:i:s') : 'N/A',
                ];
            });

            return response()->json([
                'message' => 'Shipments table data retrieved successfully',
                'data' => $tableData,
                'pagination' => [
                    'current_page' => $shipments->currentPage(),
                    'per_page' => $shipments->perPage(),
                    'total' => $shipments->total(),
                    'last_page' => $shipments->lastPage(),
                ],
                'debug' => [
                    'user_id' => $user->id,
                    'user_roles' => $user->getRoleNames(),
                    'query_count' => $shipments->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve shipments table data',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : 'Enable debug mode for trace',
            ], 500);
        }
    }

    /**
     * Get status color for UI display
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'created' => 'gray',
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
