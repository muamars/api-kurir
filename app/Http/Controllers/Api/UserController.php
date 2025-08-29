<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function getDrivers(): JsonResponse
    {
        $drivers = User::role('Kurir')
            ->where('is_active', true)
            ->with('division')
            ->get(['id', 'name', 'phone', 'division_id']);

        return response()->json([
            'data' => $drivers
        ]);
    }

    public function getUsers(Request $request): JsonResponse
    {
        $query = User::with('division', 'roles');

        if ($request->has('division_id')) {
            $query->where('division_id', $request->division_id);
        }

        if ($request->has('role')) {
            $query->role($request->role);
        }

        $users = $query->where('is_active', true)
            ->get(['id', 'name', 'email', 'phone', 'division_id']);

        return response()->json([
            'data' => $users
        ]);
    }
}
