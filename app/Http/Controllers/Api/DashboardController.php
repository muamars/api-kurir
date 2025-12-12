<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\User;
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
}
