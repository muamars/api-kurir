<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Permissions ─────────────────────────────────────────────────────────
        $permissions = [
            // Dashboard
            'view-dashboard', 'view-analytics',

            // Shipment
            'view-shipments', 'create-shipments', 'edit-shipments',
            'delete-shipments', 'approve-shipments', 'assign-drivers',

            // Progress
            'view-progress', 'update-progress', 'view-driver-history',

            // User management
            'view-users', 'create-users', 'edit-users', 'delete-users',

            // Role management
            'view-roles', 'create-roles', 'edit-roles', 'delete-roles',

            // Permission management
            'view-permissions', 'create-permissions', 'edit-permissions', 'delete-permissions',

            // Division management
            'view-divisions', 'create-divisions', 'edit-divisions', 'delete-divisions',

            // Notifications
            'view-notifications', 'manage-notifications',

            // Files
            'upload-files', 'download-files', 'manage-files',

            // Blog
            'view blogs', 'create blogs', 'edit blogs', 'delete blogs',

            // Project
            'view projects', 'create projects', 'edit projects', 'delete projects',

            // System (Super Admin only)
            'manage-system', 'manage-datamaster',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ── Roles ────────────────────────────────────────────────────────────────
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $adminRole      = Role::firstOrCreate(['name' => 'Admin']);
        $kurirRole      = Role::firstOrCreate(['name' => 'Kurir']);
        $userRole       = Role::firstOrCreate(['name' => 'User']);

        // Super Admin: semua permission tanpa terkecuali
        $superAdminRole->syncPermissions(Permission::all());

        // Admin: hanya menu operasional pengiriman
        // Akses: /dashboard /ticket /add /pengiriman /laporan /tracking
        $adminRole->syncPermissions([
            'view-dashboard',
            'view-analytics',
            'view-shipments',
            'create-shipments',
            'edit-shipments',
            'approve-shipments',
            'assign-drivers',
            'view-progress',
            'view-notifications',
            'manage-notifications',
            'upload-files',
            'download-files',
            'view blogs',
            'view projects',
        ]);

        // Kurir: akses terbatas untuk driver
        $kurirRole->syncPermissions([
            'view-dashboard',
            'view-shipments',
            'view-progress',
            'update-progress',
            'view-driver-history',
            'view-notifications',
            'upload-files',
            'download-files',
            'view blogs',
        ]);

        // User: akses dasar pembuat tiket
        $userRole->syncPermissions([
            'view-dashboard',
            'view-shipments',
            'create-shipments',
            'view-progress',
            'view-notifications',
            'upload-files',
            'download-files',
            'view blogs',
            'create blogs',
            'view projects',
        ]);
    }
}
