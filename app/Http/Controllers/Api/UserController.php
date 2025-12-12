<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function getDrivers(): JsonResponse
    {
        $drivers = User::role('Kurir')
            ->where('is_active', true)
            ->with(['division:id,name,description'])
            ->get(['id', 'name', 'phone', 'division_id']);

        return response()->json([
            'data' => $drivers,
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

        // if ($request->has('is_active')) {
        //     $query->where('is_active', $request->boolean('is_active'));
        // }
        // else {
        //     $query->where('is_active', true);
        // }
        // tambahan
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->get(['id', 'name', 'email', 'phone', 'division_id', 'is_active']);

        return response()->json([
            'data' => $users,
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'division_id' => $validated['division_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Assign roles
        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user->load(['division', 'roles']),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $user->load(['division', 'roles']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'division_id' => $validated['division_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ];

        // Only update password if provided
        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Sync roles
        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user->fresh(['division', 'roles']),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        // Prevent deleting the last admin
        if ($user->hasRole('Admin')) {
            $adminCount = User::role('Admin')->where('id', '!=', $user->id)->count();
            if ($adminCount === 0) {
                return response()->json([
                    'message' => 'Cannot delete the last admin user',
                ], 400);
            }
        }

        // Prevent deleting self
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot delete your own account',
            ], 400);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function toggleActive(User $user): JsonResponse
    {
        // Prevent deactivating self
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Tidak dapat menonaktifkan akun Anda sendiri',
            ], 400);
        }

        // Prevent deactivating the last active admin
        if ($user->hasRole('Admin') && $user->is_active) {
            $activeAdminCount = User::role('Admin')
                ->where('id', '!=', $user->id)
                ->where('is_active', true)
                ->count();

            if ($activeAdminCount === 0) {
                return response()->json([
                    'message' => 'Tidak dapat menonaktifkan admin terakhir yang aktif',
                ], 400);
            }
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message' => "User berhasil {$status}",
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'is_active' => $user->is_active,
            ],
        ]);
    }

    public function toggleMyStatus(): JsonResponse
    {
        $user = auth()->user();

        // Only allow Kurir to toggle their own status
        if (! $user->hasRole('Kurir')) {
            return response()->json([
                'message' => 'Fitur ini hanya untuk kurir',
            ], 403);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $status = $user->is_active ? 'aktif' : 'nonaktif';
        $message = $user->is_active
            ? 'Status Anda sekarang aktif. Anda dapat menerima tugas pengiriman.'
            : 'Status Anda sekarang nonaktif. Anda tidak akan menerima tugas pengiriman baru.';

        return response()->json([
            'message' => $message,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'is_active' => $user->is_active,
                'status' => $status,
            ],
        ]);
    }

    public function getMyStatus(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'status' => $user->is_active ? 'aktif' : 'nonaktif',
            ],
        ]);
    }
}
