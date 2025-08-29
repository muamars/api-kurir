<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Division;
use Illuminate\Support\Facades\Hash;

class CourierTrackingSeeder extends Seeder
{
    public function run(): void
    {
        // Create Divisions
        $divisions = [
            ['name' => 'Sales', 'description' => 'Sales Division'],
            ['name' => 'Store', 'description' => 'Store Division'],
            ['name' => 'Gudang', 'description' => 'Warehouse Division'],
            ['name' => 'Finance', 'description' => 'Finance Division'],
            ['name' => 'Operations', 'description' => 'Operations Division'],
        ];

        foreach ($divisions as $division) {
            Division::firstOrCreate(['name' => $division['name']], $division);
        }

        // Create Permissions
        $permissions = [
            'create-shipments',
            'view-shipments',
            'update-shipments',
            'delete-shipments',
            'approve-shipments',
            'assign-drivers',
            'manage-users',
            'view-dashboard',
            'update-progress',
            'view-history',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Roles
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $kurirRole = Role::firstOrCreate(['name' => 'Kurir']);
        $userRole = Role::firstOrCreate(['name' => 'User']);

        // Assign permissions to roles
        $adminRole->givePermissionTo([
            'create-shipments',
            'view-shipments',
            'update-shipments',
            'delete-shipments',
            'approve-shipments',
            'assign-drivers',
            'manage-users',
            'view-dashboard',
            'view-history',
        ]);

        $kurirRole->givePermissionTo([
            'view-shipments',
            'update-progress',
            'view-history',
        ]);

        $userRole->givePermissionTo([
            'create-shipments',
            'view-shipments',
            'view-history',
        ]);

        // Create sample users
        $salesDivision = Division::where('name', 'Sales')->first();
        $operationsDivision = Division::where('name', 'Operations')->first();

        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'division_id' => $operationsDivision->id,
                'phone' => '081234567890',
                'is_active' => true,
            ]
        );
        $admin->assignRole('Admin');

        // Kurir user
        $kurir = User::firstOrCreate(
            ['email' => 'kurir@example.com'],
            [
                'name' => 'Kurir User',
                'password' => Hash::make('password'),
                'division_id' => $operationsDivision->id,
                'phone' => '081234567891',
                'is_active' => true,
            ]
        );
        $kurir->assignRole('Kurir');

        // Regular user
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
                'division_id' => $salesDivision->id,
                'phone' => '081234567892',
                'is_active' => true,
            ]
        );
        $user->assignRole('User');
    }
}
