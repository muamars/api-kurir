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
                $q->where('company_name', 'ILIKE', "%{$search}%")
                    ->orWhere('customer_name', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%");
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
}
