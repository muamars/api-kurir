<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TugasPengiriman;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TugasPengirimanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TugasPengiriman::orderBy('tugas');

        // Non-admin hanya lihat yang aktif
        if (! auth()->user()?->hasRole(['Admin', 'Super Admin'])) {
            $query->where('is_active', true);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tugas'      => 'required|string|max:255',
            'deskripsi'  => 'nullable|string',
            'is_active'  => 'boolean',
        ]);

        $tugas = TugasPengiriman::create([
            'tugas'     => $validated['tugas'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['message' => 'Tugas pengiriman berhasil dibuat', 'data' => $tugas], 201);
    }

    public function show(TugasPengiriman $tugasPengiriman): JsonResponse
    {
        return response()->json(['data' => $tugasPengiriman]);
    }

    public function update(Request $request, TugasPengiriman $tugasPengiriman): JsonResponse
    {
        $validated = $request->validate([
            'tugas'     => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $tugasPengiriman->update($validated);

        return response()->json(['message' => 'Tugas pengiriman berhasil diupdate', 'data' => $tugasPengiriman]);
    }

    public function toggleActive(TugasPengiriman $tugasPengiriman): JsonResponse
    {
        $tugasPengiriman->is_active = ! $tugasPengiriman->is_active;
        $tugasPengiriman->save();

        return response()->json([
            'message' => 'Status berhasil diubah',
            'data'    => $tugasPengiriman,
        ]);
    }

    public function destroy(TugasPengiriman $tugasPengiriman): JsonResponse
    {
        if ($tugasPengiriman->shipments()->exists()) {
            return response()->json([
                'message' => 'Tidak dapat menghapus tugas yang sudah digunakan di shipment.',
            ], 400);
        }

        $tugasPengiriman->delete();

        return response()->json(['message' => 'Tugas pengiriman berhasil dihapus']);
    }
}
