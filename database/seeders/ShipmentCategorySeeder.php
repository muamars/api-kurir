<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ShipmentCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Antar (Delivery)',
                'description' => 'Pengambilan barang dari lokasi pelanggan untuk diproses atau dikirim, termasuk paket umum, dokumen, maupun barang elektronik.',
                'is_active' => true,
            ],
            [
                'name' => ' Ambil (Pickup)',
                'description' => 'Pengiriman barang atau dokumen langsung ke penerima, baik untuk kebutuhan personal maupun bisnis.',
                'is_active' => true,
            ],
           
        ];

        foreach ($categories as $category) {
            \App\Models\ShipmentCategory::create($category);
        }
    }
}
