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
            [
                'name' => 'Keuangan (Invoice & Tagihan)',
                'description' => 'Pengantaran invoice, tagihan pembayaran, kwitansi, atau dokumen keuangan lainnya ke pelanggan atau mitra bisnis.',
                'is_active' => true,
            ],
            [
                'name' => 'Pembelian (Purchase)',
                'description' => 'Pembelian barang atas permintaan pelanggan seperti makanan, minuman, alat rumah tangga, obat-obatan ringan, atau kebutuhan harian lainnya.',
                'is_active' => true,
            ],
            [
                'name' => ' Produksi (Workshop/Print/Jahitan)',
                'description' => 'Pengambilan atau pengantaran barang dari tempat produksi seperti percetakan, penjahit, bengkel kerajinan, atau jasa workshop lainnya.',
                'is_active' => true,
            ],
            [
                'name' => 'Tugas Lainnya (Miscellaneous)',
                'description' => 'Layanan tambahan di luar kategori utama, termasuk pengiriman barang besar seperti furniture atau permintaan khusus pelanggan.',
                'is_active' => true,
            ],

        ];

        foreach ($categories as $category) {
            \App\Models\ShipmentCategory::create($category);
        }
    }
}
