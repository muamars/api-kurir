<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VehicleTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicleTypes = [
            [
                'name' => 'Motor',
                'code' => 'MTR',
                'description' => 'Sepeda motor untuk pengiriman cepat dan jarak dekat',
                'is_active' => true,
            ],
            [
                'name' => 'Mobil ',
                'code' => 'MBL',
                'description' => 'Mobil pick up untuk barang berukuran sedang hingga besar',
                'is_active' => true,
            ],
            [
                'name' => 'Gojek',
                'code' => 'GJK',
                'description' => 'Gojek mobil atau motor',
                'is_active' => true,
            ],
            [
                'name' => 'Grab',
                'code' => 'GRB',
                'description' => 'Grab mobil atau motor',
                'is_active' => true,
            ],
            [
                'name' => 'Lalamove',
                'code' => 'LMV',
                'description' => 'Lalamove mobil atau motor',
                'is_active' => true,
            ],

        ];

        foreach ($vehicleTypes as $vehicleType) {
            \App\Models\VehicleType::create($vehicleType);
        }
    }
}
