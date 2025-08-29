<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::with('roles')->get();

        return response()->json([
            'data' => $permissions
        ]);
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {

        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'web'
        ]);

        return response()->json([
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'data' => $permission->load('roles')
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {

        $permission->update([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? $permission->guard_name
        ]);

        return response()->json([
            'message' => 'Permission updated successfully',
            'data' => $permission
        ]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        // Prevent deletion of core permissions
        $corePermissions = [
            'view-dashboard',
            'manage-shipments',
            'approve-shipments',
            'assign-drivers',
            'update-progress',
            'manage-users',
            'manage-roles'
        ];

        if (in_array($permission->name, $corePermissions)) {
            return response()->json([
                'message' => 'Cannot delete core permissions'
            ], 400);
        }

        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully'
        ]);
    }

    public function getByGroup(): JsonResponse
    {
        $permissions = Permission::all();

        $grouped = $permissions->groupBy(function ($permission) {
            $parts = explode('-', $permission->name);
            return count($parts) > 1 ? $parts[0] : 'general';
        });

        return response()->json([
            'data' => $grouped
        ]);
    }
}
