<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
            ['name' => 'Kurir', 'description' => 'Operations Division'],
            ['name' => 'Marketing', 'description' => 'Marketing Division'],
            ['name' => 'Finishing', 'description' => 'Finishing Division'],
            ['name' => 'CGO', 'description' => 'Computer Graphics Operator Division'],
            ['name' => 'Chasier/Cusromer Service', 'description' => 'Cusromer Servic and cashier Division'],
            ['name' => 'HRD', 'description' => 'Operations Division'],
            ['name' => 'Operator Indoor/Outdoor', 'description' => 'Operator Indoor/Outdoor Division'],
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
        $kurirDivision = Division::where('name', 'Kurir')->first();

        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'division_id' => $kurirDivision->id ?? 1,
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
                'division_id' => $kurirDivision->id ?? 1,
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
                'division_id' => $salesDivision->id ?? 1,
                'phone' => '081234567892',
                'is_active' => true,
            ]
        );
        $user->assignRole('User');
    }
}
