<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. Roles & Permissions (harus pertama)
            RolePermissionSeeder::class,

            // 2. Master Data
            ShipmentCategorySeeder::class,
            VehicleTypeSeeder::class,
            CustomerSeeder::class,

            // 3. Sample Data (Users, Shipments, etc)
            CourierTrackingSeeder::class,
        ]);
    }
}
