<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions for courier tracking system
        $permissions = [
            // Dashboard permissions
            'view-dashboard',
            'view-analytics',

            // Shipment permissions
            'view-shipments',
            'create-shipments',
            'edit-shipments',
            'delete-shipments',
            'approve-shipments',
            'assign-drivers',

            // Progress tracking permissions
            'view-progress',
            'update-progress',
            'view-driver-history',

            // User management permissions
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',

            // Role management permissions
            'view-roles',
            'create-roles',
            'edit-roles',
            'delete-roles',

            // Permission management
            'view-permissions',
            'create-permissions',
            'edit-permissions',
            'delete-permissions',

            // Division management
            'view-divisions',
            'create-divisions',
            'edit-divisions',
            'delete-divisions',

            // Notification permissions
            'view-notifications',
            'manage-notifications',

            // File management permissions
            'upload-files',
            'download-files',
            'manage-files',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles for courier tracking system
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $kurirRole = Role::firstOrCreate(['name' => 'Kurir']);
        $userRole = Role::firstOrCreate(['name' => 'User']);

        // Assign permissions to Admin role (full access)
        $adminRole->givePermissionTo([
            'view-dashboard',
            'view-analytics',
            'view-shipments',
            'create-shipments',
            'edit-shipments',
            'delete-shipments',
            'approve-shipments',
            'assign-drivers',
            'view-progress',
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
            'view-roles',
            'create-roles',
            'edit-roles',
            'delete-roles',
            'view-permissions',
            'create-permissions',
            'edit-permissions',
            'delete-permissions',
            'view-divisions',
            'create-divisions',
            'edit-divisions',
            'delete-divisions',
            'view-notifications',
            'manage-notifications',
            'upload-files',
            'download-files',
            'manage-files',
        ]);

        // Assign permissions to Kurir role (driver-specific)
        $kurirRole->givePermissionTo([
            'view-dashboard',
            'view-shipments',
            'view-progress',
            'update-progress',
            'view-driver-history',
            'view-notifications',
            'upload-files',
            'download-files',
        ]);

        // Assign permissions to User role (basic user)
        $userRole->givePermissionTo([
            'view-dashboard',
            'view-shipments',
            'create-shipments',
            'view-progress',
            'view-notifications',
            'upload-files',
            'download-files',
        ]);

        // Create default users (will be handled by CourierTrackingSeeder)
        // This seeder only handles roles and permissions
    }
}
