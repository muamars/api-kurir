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
            ->with(['division:id,name,description'])
            ->get(['id', 'name', 'phone', 'division_id']);

        return response()->json([
            'data' => $drivers
        ]);
    }

    public function getUsers(Request $request): JsonResponse
    {
        $query = User::with(['division:id,name,description', 'roles:id,name']);

        if ($request->has('division_id')) {
            $query->where('division_id', $request->division_id);
        }

        if ($request->has('role')) {
            $query->role($request->role);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->where('is_active', true);
        }

        $users = $query->get(['id', 'name', 'email', 'phone', 'division_id', 'is_active']);

        return response()->json([
            'data' => $users
        ]);
    }
}
