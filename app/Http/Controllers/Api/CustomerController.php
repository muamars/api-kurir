<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by company name or customer name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'LIKE', "%{$search}%")
                    ->orWhere('customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $customers = $query->orderBy('company_name')->paginate($perPage);

        return response()->json($customers);
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        return response()->json([
            'message' => 'Customer berhasil ditambahkan',
            'data' => $customer,
        ], 201);
    }

    public function show(Customer $customer)
    {
        $customer->load(['shipmentDestinations.shipment']);

        return response()->json([
            'data' => $customer,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return response()->json([
            'message' => 'Customer berhasil diupdate',
            'data' => $customer,
        ]);
    }

    public function destroy(Customer $customer)
    {
        // Check if customer has shipment destinations
        if ($customer->shipmentDestinations()->count() > 0) {
            return response()->json([
                'message' => 'Customer tidak dapat dihapus karena memiliki riwayat pengiriman',
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer berhasil dihapus',
        ]);
    }

    /**
     * Search customers with comprehensive filtering
     */
    public function search(Request $request)
    {
        $query = Customer::query();

        // Search across multiple fields
        if ($request->has('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'LIKE', "%{$search}%")
                    ->orWhere('customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        // Filter by specific fields
        if ($request->has('company_name')) {
            $query->where('company_name', 'LIKE', "%{$request->company_name}%");
        }

        if ($request->has('customer_name')) {
            $query->where('customer_name', 'LIKE', "%{$request->customer_name}%");
        }

        if ($request->has('phone')) {
            $query->where('phone', 'LIKE', "%{$request->phone}%");
        }

        if ($request->has('email')) {
            $query->where('email', 'LIKE', "%{$request->email}%");
        }

        if ($request->has('address')) {
            $query->where('address', 'LIKE', "%{$request->address}%");
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sorting options
        $sortBy = $request->get('sort_by', 'company_name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        $allowedSortFields = ['company_name', 'customer_name', 'phone', 'email', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('company_name', 'asc');
        }

        // Get all results without pagination
        $customers = $query->get();

        return response()->json([
            'message' => 'Data customer berhasil ditemukan',
            'data' => $customers,
            'total' => $customers->count(),
            'search_params' => [
                'q' => $request->q,
                'company_name' => $request->company_name,
                'customer_name' => $request->customer_name,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'is_active' => $request->is_active,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]
        ]);
    }
}
