<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan role sudah ada
        $role = Role::firstOrCreate(['name' => 'Super Admin']);

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@kurirbs.com'],
            [
                'name'      => 'Super Admin',
                'password'  => Hash::make('superadmin123'),
                'phone'     => '081200000000',
                'is_active' => true,
            ]
        );

        // Assign role Super Admin (tidak duplikat jika sudah ada)
        $superAdmin->syncRoles([$role]);

        $this->command->info('✅ Super Admin berhasil dibuat:');
        $this->command->table(
            ['Field', 'Value'],
            [
                ['Email',    $superAdmin->email],
                ['Password', 'superadmin123'],
                ['Role',     'Super Admin'],
                ['Status',   'Aktif'],
            ]
        );
    }
}
