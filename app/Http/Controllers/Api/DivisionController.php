<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDivisionRequest;
use App\Http\Requests\UpdateDivisionRequest;
use App\Models\Division;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DivisionController extends Controller
{
    /**
     * Display a listing of divisions.
     */
    public function index(): JsonResponse
    {
        $divisions = Division::select('id', 'name', 'description')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar divisi berhasil diambil.',
            'data' => $divisions,
        ]);
    }

    /**
     * Store a newly created division.
     */
    public function store(StoreDivisionRequest $request): JsonResponse
    {
        try {
            $division = Division::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Divisi berhasil dibuat.',
                'data' => $division,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat divisi.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified division.
     */
    public function show(Division $division): JsonResponse
    {
        $division->load('users:id,name,email,division_id');
        $division->loadCount('users');

        return response()->json([
            'success' => true,
            'message' => 'Detail divisi berhasil diambil.',
            'data' => $division,
        ]);
    }

    /**
     * Update the specified division.
     */
    public function update(UpdateDivisionRequest $request, Division $division): JsonResponse
    {
        try {
            $division->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Divisi berhasil diperbarui.',
                'data' => $division->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui divisi.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified division.
     */
    public function destroy(Division $division): JsonResponse
    {
        try {
            // Check if division has users
            if ($division->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus divisi yang masih memiliki pengguna.',
                ], Response::HTTP_CONFLICT);
            }

            $division->delete();

            return response()->json([
                'success' => true,
                'message' => 'Divisi berhasil dihapus.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus divisi.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
