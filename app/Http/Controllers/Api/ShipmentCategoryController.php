<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentCategoryRequest;
use App\Http\Requests\UpdateShipmentCategoryRequest;
use App\Http\Resources\ShipmentCategoryResource;
use App\Models\ShipmentCategory;
use Illuminate\Http\Request;

class ShipmentCategoryController extends Controller
{
    /**
     * shipment-categories.index.
     */
    public function index(Request $request)
    {
        $query = ShipmentCategory::query()->withCount('shipments');

        // Non-admin users only see active categories
        if (! $request->user()->hasRole('Admin')) {
            $query->where('is_active', true);
        }

        $categories = $query->orderBy('name')->get();

        return ShipmentCategoryResource::collection($categories);
    }

    /**
     * shipment-categories.store.
     */
    public function store(StoreShipmentCategoryRequest $request)
    {
        $category = ShipmentCategory::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return new ShipmentCategoryResource($category);
    }

    /**
     * shipment-categories.show.
     */
    public function show(ShipmentCategory $shipmentCategory)
    {
        $shipmentCategory->loadCount('shipments');

        return new ShipmentCategoryResource($shipmentCategory);
    }

    /**
     * shipment-categories.update.
     */
    public function update(UpdateShipmentCategoryRequest $request, ShipmentCategory $shipmentCategory)
    {
        $shipmentCategory->update($request->only(['name', 'description', 'is_active']));

        return new ShipmentCategoryResource($shipmentCategory);
    }

    /**
     * shipment-categories.delete.
     */
    public function destroy(ShipmentCategory $shipmentCategory)
    {
        $shipmentsCount = $shipmentCategory->shipments()->count();

        if ($shipmentsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete category with associated shipments',
                'shipments_count' => $shipmentsCount,
            ], 400);
        }

        $shipmentCategory->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
