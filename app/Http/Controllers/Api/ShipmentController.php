<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Shipment::with(['creator', 'approver', 'driver', 'destinations', 'items']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter for driver's assigned shipments
        if ($request->has('driver_id')) {
            $query->where('assigned_driver_id', $request->driver_id);
        }

        // Filter by division (for requesters)
        if ($request->has('division_id')) {
            $query->whereHas('creator', function ($q) use ($request) {
                $q->where('division_id', $request->division_id);
            });
        }

        // Date range filter
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search by shipment ID, receiver name, or delivery address
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('shipment_id', 'ILIKE', "%{$search}%")
                    ->orWhere('notes', 'ILIKE', "%{$search}%")
                    ->orWhereHas('destinations', function ($destQuery) use ($search) {
                        $destQuery->where('receiver_name', 'ILIKE', "%{$search}%")
                            ->orWhere('delivery_address', 'ILIKE', "%{$search}%");
                    });
            });
        }

        // Filter by creator
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // Role-based filtering
        $user = $request->user();
        if (!$user->hasRole('Admin')) {
            if ($user->hasRole('Kurir')) {
                $query->where('assigned_driver_id', $user->id);
            } else {
                $query->where('created_by', $user->id);
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if ($sortBy === 'priority') {
            $query->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END " . $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Default secondary sort
        if ($sortBy !== 'created_at') {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 15);
        $shipments = $query->paginate($perPage);

        return response()->json(ShipmentResource::collection($shipments));
    }

    public function store(StoreShipmentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $shipment = Shipment::create([
                'shipment_id' => 'SPJ-' . date('Ymd') . '-' . Str::random(6),
                'created_by' => auth()->id(),
                'status' => 'pending',
                'notes' => $request->notes,
                'priority' => $request->priority ?? 'regular',
                'deadline' => $request->deadline,
            ]);

            // Create destinations
            foreach ($request->destinations as $index => $destination) {
                $shipment->destinations()->create([
                    'receiver_name' => $destination['receiver_name'],
                    'delivery_address' => $destination['delivery_address'],
                    'shipment_note' => $destination['shipment_note'] ?? null,
                    'sequence_order' => $index + 1,
                ]);
            }

            // Create items
            foreach ($request->items as $item) {
                $shipment->items()->create([
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'description' => $item['description'] ?? null,
                ]);
            }

            DB::commit();

            // Send notification
            app(NotificationService::class)->shipmentCreated($shipment);

            return response()->json([
                'message' => 'Shipment created successfully',
                'data' => new ShipmentResource($shipment->load(['creator', 'destinations', 'items']))
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create shipment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Shipment $shipment): JsonResponse
    {
        $shipment->load(['creator', 'approver', 'driver', 'destinations', 'items', 'progress.driver']);

        return response()->json(new ShipmentResource($shipment));
    }

    public function approve(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('approve-shipments');

        if ($shipment->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending shipments can be approved'
            ], 400);
        }

        $shipment->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Send notification
        app(NotificationService::class)->shipmentApproved($shipment->fresh(['creator', 'approver']));

        return response()->json([
            'message' => 'Shipment approved successfully',
            'data' => $shipment->fresh(['creator', 'approver'])
        ]);
    }

    public function assignDriver(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('assign-drivers');

        $request->validate([
            'driver_id' => 'required|exists:users,id'
        ]);

        if (!in_array($shipment->status, ['approved', 'assigned'])) {
            return response()->json([
                'message' => 'Only approved shipments can be assigned'
            ], 400);
        }

        $shipment->update([
            'assigned_driver_id' => $request->driver_id,
            'status' => 'assigned',
        ]);

        // Send notification
        app(NotificationService::class)->shipmentAssigned($shipment->fresh(['driver', 'creator']));

        return response()->json([
            'message' => 'Driver assigned successfully',
            'data' => $shipment->fresh(['driver'])
        ]);
    }

    public function startDelivery(Shipment $shipment): JsonResponse
    {
        if ($shipment->assigned_driver_id !== auth()->id()) {
            return response()->json([
                'message' => 'You are not assigned to this shipment'
            ], 403);
        }

        if ($shipment->status !== 'assigned') {
            return response()->json([
                'message' => 'Shipment must be assigned before starting delivery'
            ], 400);
        }

        $shipment->update(['status' => 'in_progress']);

        // Send notification
        app(NotificationService::class)->deliveryStarted($shipment->load(['creator', 'driver']));

        return response()->json([
            'message' => 'Delivery started successfully',
            'data' => $shipment
        ]);
    }
}
