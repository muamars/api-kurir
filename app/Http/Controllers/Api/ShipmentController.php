<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Shipment::with(['creator', 'approver', 'driver', 'destinations', 'items', 'category', 'vehicleType']);

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by vehicle type
        if ($request->has('vehicle_type_id')) {
            $query->where('vehicle_type_id', $request->vehicle_type_id);
        }

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
                $q->where('shipment_id', 'LIKE', "%{$search}%")
                    ->orWhere('notes', 'LIKE', "%{$search}%")
                    ->orWhereHas('destinations', function ($destQuery) use ($search) {
                        $destQuery->where('receiver_name', 'LIKE', "%{$search}%")
                            ->orWhere('delivery_address', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Filter by creator
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // Role-based filtering
        $user = $request->user();
        if (! $user->hasRole('Admin')) {
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
            $query->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END ".$sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Default secondary sort
        if ($sortBy !== 'created_at') {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 50);
        $shipments = $query->paginate($perPage);

        return response()->json([
            'data' => ShipmentResource::collection($shipments->items()),
            'pagination' => [
                'current_page' => $shipments->currentPage(),
                'last_page' => $shipments->lastPage(),
                'per_page' => $shipments->perPage(),
                'total' => $shipments->total(),
                'from' => $shipments->firstItem(),
                'to' => $shipments->lastItem(),
            ],
            'links' => [
                'first' => $shipments->url(1),
                'last' => $shipments->url($shipments->lastPage()),
                'prev' => $shipments->previousPageUrl(),
                'next' => $shipments->nextPageUrl(),
            ]
        ]);
    }

    public function store(StoreShipmentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $shipmentData = [
                'shipment_id' => 'SPJ-'.date('Ymd').'-'.Str::random(6),
                'created_by' => auth()->id(),
                'category_id' => $request->category_id,
                'vehicle_type_id' => $request->vehicle_type_id,
                'status' => 'pending', // ✅ FIXED: Use valid status from enum
                'notes' => $request->notes,
                'courier_notes' => $request->courier_notes,
                'priority' => $request->priority ?? 'regular',
                'deadline' => $request->deadline,
                'scheduled_delivery_datetime' => $request->scheduled_delivery_datetime,
            ];

            $shipment = Shipment::create($shipmentData);

            // Create destinations
            foreach ($request->destinations as $index => $destination) {
                $shipment->destinations()->create([
                    'receiver_company' => $destination['receiver_company'],
                    'receiver_name' => $destination['receiver_name'],
                    'receiver_contact' => $destination['receiver_contact'],
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

            // Send notification for shipment created
            $shipment->load(['creator', 'destinations', 'items', 'category', 'vehicleType']);
            app(NotificationService::class)->shipmentCreated($shipment);

            return response()->json([
                'message' => 'Shipment created successfully',
                'data' => new ShipmentResource($shipment),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create shipment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Shipment $shipment): JsonResponse
    {
        $shipment->load(['creator', 'approver', 'driver', 'destinations', 'items', 'progress.driver', 'category', 'vehicleType']);

        return response()->json(new ShipmentResource($shipment));
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        $shipment->update($request->only(['category_id', 'vehicle_type_id', 'notes', 'priority', 'deadline', 'status']));

        return response()->json([
            'message' => 'Shipment updated successfully',
            'data' => new ShipmentResource($shipment->load(['creator', 'approver', 'driver', 'destinations', 'items', 'category', 'vehicleType'])),
        ]);
    }

    public function approve(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('approve-shipments');

        if (! in_array($shipment->status, ['pending'])) {
            return response()->json([
                'message' => 'Only pending shipments can be approved',
            ], 400);
        }

        $request->validate([
            'driver_id' => 'required|exists:users,id',
        ]);

        $shipment->update([
            'assigned_driver_id' => $request->driver_id,
            'status' => 'assigned',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Send notification
        app(NotificationService::class)->shipmentAssigned($shipment->fresh(['creator', 'approver', 'driver']));

        return response()->json([
            'message' => 'Shipment approved and driver assigned successfully',
            'data' => $shipment->fresh(['creator', 'approver', 'driver']),
        ]);
    }

    public function assignDriver(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('assign-drivers');

        $request->validate([
            'driver_id' => 'required|exists:users,id',
        ]);

        if ($shipment->status !== 'assigned') {
            return response()->json([
                'message' => 'Only assigned shipments can be reassigned',
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
            'data' => $shipment->fresh(['driver']),
        ]);
    }

    public function bulkAssignDriver(Request $request): JsonResponse
    {
        $this->authorize('assign-drivers');

        $request->validate([
            'shipment_ids' => 'required|array|min:1',
            'shipment_ids.*' => 'required|exists:shipments,id',
            'driver_id' => 'required|exists:users,id',
            'vehicle_type_id' => [
                'required',
                'exists:vehicle_types,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $vehicleType = \App\Models\VehicleType::find($value);
                        if ($vehicleType && ! $vehicleType->is_active) {
                            $fail('The selected vehicle type is not active.');
                        }
                    }
                },
            ],
        ]);

        try {
            DB::beginTransaction();

            $shipments = Shipment::whereIn('id', $request->shipment_ids)
                ->whereIn('status', ['pending'])
                ->get();

            if ($shipments->count() !== count($request->shipment_ids)) {
                return response()->json([
                    'message' => 'Some shipments are not in pending status',
                ], 400);
            }

            $updatedCount = 0;
            foreach ($shipments as $shipment) {
                $shipment->update([
                    'assigned_driver_id' => $request->driver_id,
                    'vehicle_type_id' => $request->vehicle_type_id,
                    'status' => 'assigned',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);

                // Send notification for each shipment
                app(NotificationService::class)->shipmentAssigned($shipment->fresh(['creator', 'approver', 'driver', 'vehicleType']));
                $updatedCount++;
            }

            DB::commit();

            // Create bulk assignment record for tracking
            $bulkAssignment = \DB::table('bulk_assignments')->insertGetId([
                'admin_id' => auth()->id(),
                'driver_id' => $request->driver_id,
                'vehicle_type_id' => $request->vehicle_type_id,
                'shipment_count' => $updatedCount,
                'shipment_ids' => json_encode($request->shipment_ids),
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => "{$updatedCount} shipments assigned to driver successfully",
                'assigned_count' => $updatedCount,
                'bulk_assignment_id' => $bulkAssignment,
                'shipments' => ShipmentResource::collection($shipments->load(['creator', 'driver', 'vehicleType', 'destinations', 'items'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to assign shipments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getBulkAssignmentHistory(Request $request): JsonResponse
    {
        $query = DB::table('bulk_assignments as ba')
            ->join('users as admin', 'ba.admin_id', '=', 'admin.id')
            ->join('users as driver', 'ba.driver_id', '=', 'driver.id')
            ->join('vehicle_types as vt', 'ba.vehicle_type_id', '=', 'vt.id')
            ->select([
                'ba.id',
                'ba.shipment_count',
                'ba.shipment_ids',
                'ba.assigned_at',
                'admin.name as admin_name',
                'driver.name as driver_name',
                'vt.name as vehicle_type_name'
            ]);

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('ba.assigned_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('ba.assigned_at', '<=', $request->date_to);
        }

        // Filter by driver
        if ($request->has('driver_id')) {
            $query->where('ba.driver_id', $request->driver_id);
        }

        // Filter by admin
        if ($request->has('admin_id')) {
            $query->where('ba.admin_id', $request->admin_id);
        }

        $bulkAssignments = $query->orderBy('ba.assigned_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $bulkAssignments->items(),
            'pagination' => [
                'current_page' => $bulkAssignments->currentPage(),
                'last_page' => $bulkAssignments->lastPage(),
                'per_page' => $bulkAssignments->perPage(),
                'total' => $bulkAssignments->total(),
            ],
        ]);
    }

    public function getBulkAssignmentDetail($bulkAssignmentId): JsonResponse
    {
        $bulkAssignment = DB::table('bulk_assignments as ba')
            ->join('users as admin', 'ba.admin_id', '=', 'admin.id')
            ->join('users as driver', 'ba.driver_id', '=', 'driver.id')
            ->join('vehicle_types as vt', 'ba.vehicle_type_id', '=', 'vt.id')
            ->where('ba.id', $bulkAssignmentId)
            ->select([
                'ba.*',
                'admin.name as admin_name',
                'driver.name as driver_name',
                'vt.name as vehicle_type_name'
            ])
            ->first();

        if (!$bulkAssignment) {
            return response()->json([
                'message' => 'Bulk assignment not found'
            ], 404);
        }

        $shipmentIds = json_decode($bulkAssignment->shipment_ids);
        
        // Get shipments with their current status and timing
        $shipments = Shipment::with(['destinations.statusHistories', 'creator', 'category'])
            ->whereIn('id', $shipmentIds)
            ->get();

        $shipmentsData = [];
        foreach ($shipments as $shipment) {
            $destinationData = [];
            foreach ($shipment->destinations as $destination) {
                $timing = $this->calculateDestinationTiming($destination);
                $destinationData[] = [
                    'id' => $destination->id,
                    'delivery_address' => $destination->delivery_address,
                    'receiver_name' => $destination->receiver_name,
                    'receiver_company' => $destination->receiver_company,
                    'receiver_contact' => $destination->receiver_contact,
                    'shipment_note' => $destination->shipment_note,
                    'current_status' => $destination->status,
                    'timing' => $timing,
                ];
            }

            $shipmentsData[] = [
                'id' => $shipment->id,
                'shipment_id' => $shipment->shipment_id,
                'current_status' => $shipment->status,
                'creator' => $shipment->creator->name,
                'category' => $shipment->category ? [
                    'id' => $shipment->category->id,
                    'name' => $shipment->category->name,
                    'description' => $shipment->category->description,
                ] : null,
                'destinations' => $destinationData,
            ];
        }

        return response()->json([
            'data' => [
                'bulk_assignment' => $bulkAssignment,
                'shipments' => $shipmentsData,
                'summary' => $this->calculateBulkSummary($shipmentsData),
            ],
        ]);
    }

    public function pending(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('assign-drivers');

        if (! in_array($shipment->status, ['pending'])) {
            return response()->json([
                'message' => 'Only pending shipments can be set to pending',
            ], 400);
        }

        $request->validate([
            'scheduled_delivery_datetime' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        $shipment->update([
            'status' => 'pending',
            'scheduled_delivery_datetime' => $request->scheduled_delivery_datetime,
        ]);

        // Send notification
        app(NotificationService::class)->shipmentPending($shipment->fresh(['creator']));

        return response()->json([
            'message' => 'Shipment set to pending successfully',
            'data' => $shipment->fresh(['creator']),
        ]);
    }

    public function startDelivery(Shipment $shipment): JsonResponse
    {
        if ($shipment->assigned_driver_id !== auth()->id()) {
            return response()->json([
                'message' => 'You are not assigned to this shipment',
            ], 403);
        }

        if (! in_array($shipment->status, ['assigned', 'pending'])) {
            return response()->json([
                'message' => 'Shipment must be assigned or pending before starting delivery',
            ], 400);
        }

        // Validasi: Harus ada minimal 1 destination yang sudah di-pickup
        $pickedCount = $shipment->destinations()->where('status', 'picked')->count();
        if ($pickedCount === 0) {
            return response()->json([
                'message' => 'Please pickup at least one item before starting delivery',
                'hint' => 'Use POST /shipments/{id}/destinations/{destination_id}/progress with status=picked',
            ], 400);
        }

        // update shipment
        $shipment->update(['status' => 'in_progress']);

        // update semua destinasi yg sudah picked jadi in_progress
        $shipment->destinations()
            ->where('status', 'picked')
            ->update(['status' => 'in_progress']);

        // kirim notifikasi
        app(NotificationService::class)->deliveryStarted($shipment->load(['creator', 'driver']));

        return response()->json([
            'message' => 'Delivery started successfully',
            'data' => $shipment->load('destinations'),
        ]);
    }

    public function cancel(Request $request, Shipment $shipment): JsonResponse
    {
        if (! $request->user() || ! $request->user()->can('approve-shipments')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (! in_array($shipment->status, ['pending'])) {
            return response()->json([
                'message' => 'Only pending shipments can be cancelled',
            ], 400);
        }

        $shipment->update([
            'status' => 'cancelled',
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
        ]);

        app(\App\Services\NotificationService::class)->shipmentCancelled($shipment->fresh(['creator', 'driver']));

        return response()->json([
            'message' => 'Shipment cancelled successfully',
            'data' => $shipment->fresh(['creator', 'driver']),
        ]);
    }

    /**
     * Get bulk assignments for current driver (Kurir only)
     */
    public function getMyBulkAssignments(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Only drivers can access this endpoint
            if (!$user->hasRole('Kurir')) {
                return response()->json([
                    'message' => 'This endpoint is only for drivers'
                ], 403);
            }

            $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:50',
                'status' => 'nullable|in:assigned,in_progress,completed,all',
            ]);

            $perPage = $request->get('per_page', 10);

            // Get bulk assignments for this driver
            $query = DB::table('bulk_assignments as ba')
                ->join('users as admin', 'ba.admin_id', '=', 'admin.id')
                ->join('vehicle_types as vt', 'ba.vehicle_type_id', '=', 'vt.id')
                ->where('ba.driver_id', $user->id)
                ->select([
                    'ba.id',
                    'ba.shipment_count',
                    'ba.shipment_ids',
                    'ba.assigned_at',
                    'admin.name as admin_name',
                    'vt.name as vehicle_type_name'
                ])
                ->orderBy('ba.assigned_at', 'desc');

            $bulkAssignments = $query->paginate($perPage);

            // Transform data for driver view - ONLY show bulk assignments with active shipments
            $driverData = $bulkAssignments->getCollection()->map(function ($bulkAssignment) use ($request, $user) {
                $shipmentIds = json_decode($bulkAssignment->shipment_ids);
                
                // Get shipments with current status - ONLY active shipments still assigned to this driver
                $shipmentsQuery = Shipment::with([
                    'destinations:id,shipment_id,delivery_address,receiver_name,receiver_contact,status',
                    'items:id,shipment_id,item_name,quantity',
                    'creator:id,name',
                    'category:id,name,description'
                ])
                ->whereIn('id', $shipmentIds)
                ->where('assigned_driver_id', $user->id) // ✅ FILTER: Only shipments still assigned to this driver
                ->whereNotIn('status', ['cancelled']); // ✅ FILTER: Exclude cancelled shipments only

                // Additional status filter if requested
                if ($request->filled('status') && $request->status !== 'all') {
                    $shipmentsQuery->where('status', $request->status);
                }

                $shipments = $shipmentsQuery->get();

                // ✅ SKIP bulk assignment if no shipments found
                if ($shipments->isEmpty()) {
                    return null;
                }

                // Sort shipments according to the original order selected by admin
                $orderedShipments = collect();
                foreach ($shipmentIds as $id) {
                    $shipment = $shipments->firstWhere('id', $id);
                    if ($shipment) {
                        $orderedShipments->push($shipment);
                    }
                }

                // Calculate summary for this bulk assignment
                $summary = $this->calculateDriverBulkSummary($orderedShipments);

                // Transform shipments data for driver
                $shipmentsData = $orderedShipments->map(function ($shipment) {
                    return [
                        'id' => $shipment->id,
                        'shipment_id' => $shipment->shipment_id,
                        'current_status' => $shipment->status,
                        'status_label' => $this->getShipmentStatusLabel($shipment->status),
                        'priority' => $shipment->priority,
                        'priority_label' => ucfirst($shipment->priority),
                        'creator' => $shipment->creator ? $shipment->creator->name : 'Unknown',
                        'category' => $shipment->category ? [
                            'id' => $shipment->category->id,
                            'name' => $shipment->category->name,
                            'description' => $shipment->category->description,
                        ] : null,
                        'deadline' => $shipment->deadline ? $shipment->deadline->format('Y-m-d H:i') : null,
                        'is_overdue' => $shipment->deadline && $shipment->deadline < now() && $shipment->status !== 'completed',
                        'destinations' => $shipment->destinations->map(function ($dest) {
                            return [
                                'id' => $dest->id,
                                'delivery_address' => $dest->delivery_address,
                                'receiver_name' => $dest->receiver_name,
                                'receiver_contact' => $dest->receiver_contact ?? '',
                                'current_status' => $dest->status,
                                'status_label' => $this->getDestinationStatusLabel($dest->status),
                            ];
                        }),
                        'items' => $shipment->items->map(function ($item) {
                            return [
                                'item_name' => $item->item_name,
                                'quantity' => $item->quantity,
                            ];
                        }),
                        'total_items' => $shipment->items->count(),
                        'total_destinations' => $shipment->destinations->count(),
                    ];
                });

                return [
                    'bulk_assignment_id' => $bulkAssignment->id,
                    'assigned_by' => $bulkAssignment->admin_name,
                    'vehicle_type' => $bulkAssignment->vehicle_type_name,
                    'assigned_at' => $bulkAssignment->assigned_at,
                    'assigned_at_formatted' => \Carbon\Carbon::parse($bulkAssignment->assigned_at)->format('d M Y, H:i'),
                    'days_since_assigned' => \Carbon\Carbon::parse($bulkAssignment->assigned_at)->diffInDays(now()),
                    'total_shipments' => $bulkAssignment->shipment_count,
                    'summary' => $summary,
                    'shipments' => $shipmentsData,
                ];
            })->filter(); // ✅ Remove null entries (bulk assignments with no active shipments)

            return response()->json([
                'message' => 'Driver bulk assignments retrieved successfully',
                'data' => $driverData,
                'pagination' => [
                    'current_page' => $bulkAssignments->currentPage(),
                    'per_page' => $bulkAssignments->perPage(),
                    'total' => $bulkAssignments->total(),
                    'last_page' => $bulkAssignments->lastPage(),
                    'from' => $bulkAssignments->firstItem(),
                    'to' => $bulkAssignments->lastItem(),
                    'has_more_pages' => $bulkAssignments->hasMorePages(),
                ],
                'filters' => [
                    'status' => $request->status ?? 'all',
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve bulk assignments',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Get specific bulk assignment detail for current driver
     */
    public function getMyBulkAssignmentDetail(Request $request, $bulkAssignmentId): JsonResponse
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

        $shipmentIds = json_decode($bulkAssignment->shipment_ids);
        
        // Get detailed shipments data - Show all shipments in bulk assignment that are still assigned to this driver
        $shipments = Shipment::with([
            'destinations.statusHistories',
            'items',
            'creator:id,name,email',
            'photos',
            'category:id,name,description'
        ])
        ->whereIn('id', $shipmentIds)
        ->where('assigned_driver_id', $user->id) // ✅ FILTER: Only shipments still assigned to this driver
        ->whereNotIn('status', ['cancelled']) // ✅ FILTER: Exclude cancelled shipments only
        ->get();

        // ✅ If no shipments found, return not found
        if ($shipments->isEmpty()) {
            return response()->json([
                'message' => 'No shipments found in this bulk assignment for current driver'
            ], 404);
        }

        // Sort shipments according to the original order selected by admin
        $orderedShipments = collect();
        foreach ($shipmentIds as $id) {
            $shipment = $shipments->firstWhere('id', $id);
            if ($shipment) {
                $orderedShipments->push($shipment);
            }
        }

        // Transform detailed data for driver
        $detailedShipments = $orderedShipments->map(function ($shipment) {
            $destinationsData = $shipment->destinations->map(function ($destination) {
                $timing = $this->calculateDestinationTiming($destination);
                
                return [
                    'id' => $destination->id,
                    'delivery_address' => $destination->delivery_address,
                    'receiver_name' => $destination->receiver_name,
                    'receiver_contact' => $destination->receiver_contact,
                    'receiver_company' => $destination->receiver_company,
                    'current_status' => $destination->status,
                    'status_label' => $this->getDestinationStatusLabel($destination->status),
                    'shipment_note' => $destination->shipment_note,
                    'timing' => $timing,
                ];
            });

            return [
                'id' => $shipment->id,
                'shipment_id' => $shipment->shipment_id,
                'current_status' => $shipment->status,
                'status_label' => $this->getShipmentStatusLabel($shipment->status),
                'priority' => $shipment->priority,
                'priority_label' => ucfirst($shipment->priority),
                'notes' => $shipment->notes,
                'deadline' => $shipment->deadline ? $shipment->deadline->format('Y-m-d H:i') : null,
                'deadline_formatted' => $shipment->deadline ? $shipment->deadline->format('d M Y, H:i') : null,
                'is_overdue' => $shipment->deadline && $shipment->deadline < now() && $shipment->status !== 'completed',
                'creator' => [
                    'name' => $shipment->creator->name,
                    'email' => $shipment->creator->email,
                ],
                'category' => $shipment->category ? [
                    'id' => $shipment->category->id,
                    'name' => $shipment->category->name,
                    'description' => $shipment->category->description,
                ] : null,
                'destinations' => $destinationsData,
                'items' => $shipment->items->map(function ($item) {
                    return [
                        'item_name' => $item->item_name,
                        'quantity' => $item->quantity,
                        'description' => $item->description,
                    ];
                }),
                'photos' => $shipment->photos->map(function ($photo) {
                    return [
                        'id' => $photo->id,
                        'type' => $photo->type,
                        'photo_url' => $photo->photo_url,
                        'photo_thumbnail' => $photo->photo_thumbnail,
                        'notes' => $photo->notes,
                        'uploaded_at' => $photo->uploaded_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'totals' => [
                    'items' => $shipment->items->count(),
                    'destinations' => $shipment->destinations->count(),
                ],
            ];
        });

        // Calculate comprehensive summary
        $comprehensiveSummary = $this->calculateDriverBulkSummary($orderedShipments);

        return response()->json([
            'message' => 'Bulk assignment detail retrieved successfully',
            'data' => [
                'bulk_assignment' => [
                    'id' => $bulkAssignment->id,
                    'assigned_by' => $bulkAssignment->admin_name,
                    'vehicle_type' => $bulkAssignment->vehicle_type_name,
                    'assigned_at' => $bulkAssignment->assigned_at,
                    'assigned_at_formatted' => \Carbon\Carbon::parse($bulkAssignment->assigned_at)->format('d M Y, H:i'),
                    'days_since_assigned' => \Carbon\Carbon::parse($bulkAssignment->assigned_at)->diffInDays(now()),
                    'total_shipments' => $bulkAssignment->shipment_count,
                ],
                'shipments' => $detailedShipments,
                'summary' => $comprehensiveSummary,
            ],
        ]);
    }

    // Helper methods
    private function calculateDestinationTiming($destination): array
    {
        $histories = $destination->statusHistories()
            ->orderBy('changed_at', 'asc')
            ->get();

        if ($histories->isEmpty()) {
            return ['status' => 'No timing data available'];
        }

        $statusTimes = [];
        foreach ($histories as $history) {
            $statusTimes[$history->new_status] = $history->changed_at;
        }

        $timing = [];

        // Pickup to Delivery
        if (isset($statusTimes['pending']) && isset($statusTimes['delivered'])) {
            $duration = $statusTimes['delivered']->diff($statusTimes['pending']);
            $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
            
            $timing['pickup_to_delivery'] = [
                'start_time' => $statusTimes['pending']->format('Y-m-d H:i:s'),
                'end_time' => $statusTimes['delivered']->format('Y-m-d H:i:s'),
                'duration_minutes' => $totalMinutes,
                'duration_human' => $this->formatDurationSimple($duration),
            ];
        }

        // Return to Office
        if (isset($statusTimes['returning']) && isset($statusTimes['finished'])) {
            $duration = $statusTimes['finished']->diff($statusTimes['returning']);
            $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
            
            $timing['return_to_office'] = [
                'start_time' => $statusTimes['returning']->format('Y-m-d H:i:s'),
                'end_time' => $statusTimes['finished']->format('Y-m-d H:i:s'),
                'duration_minutes' => $totalMinutes,
                'duration_human' => $this->formatDurationSimple($duration),
            ];
        }

        // Total Duration
        if (isset($statusTimes['pending']) && isset($statusTimes['finished'])) {
            $duration = $statusTimes['finished']->diff($statusTimes['pending']);
            $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
            
            $timing['total_duration'] = [
                'duration_minutes' => $totalMinutes,
                'duration_human' => $this->formatDurationSimple($duration),
            ];
        }

        return $timing;
    }

    private function formatDurationSimple(\DateInterval $duration): string
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

        return empty($parts) ? '0 menit' : implode(' ', $parts);
    }

    private function calculateBulkSummary(array $shipmentsData): array
    {
        $totalShipments = count($shipmentsData);
        $completedShipments = 0;
        $totalPickupTime = 0;
        $totalReturnTime = 0;
        $pickupTimes = [];

        foreach ($shipmentsData as $shipment) {
            if ($shipment['current_status'] === 'completed') {
                $completedShipments++;
            }

            foreach ($shipment['destinations'] as $destination) {
                if (isset($destination['timing']['pickup_to_delivery']['duration_minutes'])) {
                    $pickupTime = $destination['timing']['pickup_to_delivery']['duration_minutes'];
                    $totalPickupTime += $pickupTime;
                    $pickupTimes[] = $pickupTime;
                }

                if (isset($destination['timing']['return_to_office']['duration_minutes'])) {
                    $totalReturnTime += $destination['timing']['return_to_office']['duration_minutes'];
                }
            }
        }

        $summary = [
            'total_shipments' => $totalShipments,
            'completed_shipments' => $completedShipments,
            'completion_rate' => $totalShipments > 0 ? round(($completedShipments / $totalShipments) * 100, 2) : 0,
        ];

        if (!empty($pickupTimes)) {
            $summary['pickup_analysis'] = [
                'fastest_delivery' => min($pickupTimes) . ' menit',
                'slowest_delivery' => max($pickupTimes) . ' menit',
                'average_delivery' => round(array_sum($pickupTimes) / count($pickupTimes), 2) . ' menit',
                'total_delivery_time' => array_sum($pickupTimes) . ' menit',
            ];
        }

        if ($totalReturnTime > 0) {
            $summary['return_analysis'] = [
                'total_return_time' => $totalReturnTime . ' menit',
                'average_return_time' => round($totalReturnTime / $totalShipments, 2) . ' menit',
            ];
        }

        return $summary;
    }

    /**
     * Calculate summary for driver bulk assignment
     */
    private function calculateDriverBulkSummary($shipments): array
    {
        $totalShipments = $shipments->count();
        $statusCounts = [
            'assigned' => 0,
            'in_progress' => 0,
            'completed' => 0,
        ];

        $totalDestinations = 0;
        $completedDestinations = 0;
        $overdueShipments = 0;
        $totalItems = 0;

        foreach ($shipments as $shipment) {
            // Count by status
            if (isset($statusCounts[$shipment->status])) {
                $statusCounts[$shipment->status]++;
            }

            // Check overdue
            if ($shipment->deadline && $shipment->deadline < now() && $shipment->status !== 'completed') {
                $overdueShipments++;
            }

            // Count destinations and items
            $totalDestinations += $shipment->destinations->count();
            $totalItems += $shipment->items->count();

            // Count completed destinations
            $completedDestinations += $shipment->destinations->where('status', 'finished')->count();
        }

        $completionRate = $totalShipments > 0 ? round(($statusCounts['completed'] / $totalShipments) * 100, 2) : 0;
        $destinationCompletionRate = $totalDestinations > 0 ? round(($completedDestinations / $totalDestinations) * 100, 2) : 0;

        return [
            'total_shipments' => $totalShipments,
            'status_breakdown' => [
                'assigned' => $statusCounts['assigned'],
                'in_progress' => $statusCounts['in_progress'],
                'completed' => $statusCounts['completed'],
            ],
            'completion_rate' => $completionRate,
            'destination_completion_rate' => $destinationCompletionRate,
            'overdue_shipments' => $overdueShipments,
            'totals' => [
                'destinations' => $totalDestinations,
                'completed_destinations' => $completedDestinations,
                'items' => $totalItems,
            ],
        ];
    }

    /**
     * Get destination status label
     */
    private function getDestinationStatusLabel(string $status): string
    {
        switch ($status) {
            case 'pending':
                return 'Menunggu Pickup';
            case 'picked':
                return 'Sudah Dipickup';
            case 'in_progress':
                return 'Dalam Perjalanan';
            case 'arrived':
                return 'Sampai di Lokasi';
            case 'delivered':
                return 'Sudah Diterima';
            case 'completed':
                return 'Selesai';
            case 'returning':
                return 'Perjalanan Pulang';
            case 'finished':
                return 'Sampai di Kantor';
            case 'takeover':
                return 'Takeover';
            case 'failed':
                return 'Gagal';
            default:
                return ucfirst($status);
        }
    }

    /**
     * Get shipment status label
     */
    private function getShipmentStatusLabel(string $status): string
    {
        switch ($status) {
            case 'pending':
                return 'Menunggu Persetujuan';
            case 'approved':
                return 'Disetujui';
            case 'assigned':
                return 'Ditugaskan';
            case 'in_progress':
                return 'Dalam Perjalanan';
            case 'completed':
                return 'Selesai';
            case 'cancelled':
                return 'Dibatalkan';
            default:
                return ucfirst($status);
        }
    }
}