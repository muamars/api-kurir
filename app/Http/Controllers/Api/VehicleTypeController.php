<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleTypeResource;
use App\Models\VehicleType;
use Illuminate\Http\Request;

class VehicleTypeController extends Controller
{
    /**
     * Vehicle.index.
     */
    public function index(Request $request)
    {
        $query = VehicleType::query()->withCount('shipments');

        // Non-admin users only see active vehicle types
        if (! $request->user()->hasRole('Admin')) {
            $query->where('is_active', true);
        }

        $vehicleTypes = $query->orderBy('name')->get();

        return VehicleTypeResource::collection($vehicleTypes);
    }

    /**
     * Vehicle.store.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:vehicle_types,code',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $vehicleType = VehicleType::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return new VehicleTypeResource($vehicleType);
    }

    /**
     * Vehicle.show.
     */
    public function show(VehicleType $vehicleType)
    {
        $vehicleType->loadCount('shipments');

        return new VehicleTypeResource($vehicleType);
    }

    /**
     * Vehicle.update.
     */
    public function update(Request $request, VehicleType $vehicleType)
    {
        $request->validate([
            'name' => 'string|max:255',
            'code' => 'string|max:255|unique:vehicle_types,code,'.$vehicleType->id,
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $vehicleType->update($request->only(['name', 'code', 'description', 'is_active']));

        return new VehicleTypeResource($vehicleType);
    }

    /**
     *Vehicle.delete.
     */
    public function destroy(VehicleType $vehicleType)
    {
        $shipmentsCount = $vehicleType->shipments()->count();

        if ($shipmentsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete vehicle type with associated shipments',
                'shipments_count' => $shipmentsCount,
            ], 400);
        }

        $vehicleType->delete();

        return response()->json([
            'message' => 'Vehicle type deleted successfully',
        ]);
    }
}
