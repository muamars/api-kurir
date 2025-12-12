<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'company_name' => 'PT Maju Jaya',
                'customer_name' => 'Budi Santoso',
                'phone' => '081234567890',
                'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
                'is_active' => true,
            ],
            [
                'company_name' => 'CV Berkah Sejahtera',
                'customer_name' => 'Siti Nurhaliza',
                'phone' => '081298765432',
                'address' => 'Jl. Gatot Subroto No. 45, Jakarta Selatan',
                'is_active' => true,
            ],
            [
                'company_name' => 'PT Teknologi Indonesia',
                'customer_name' => 'Ahmad Fauzi',
                'phone' => '081345678901',
                'address' => 'Jl. HR Rasuna Said Kav. 10, Jakarta Selatan',
                'is_active' => true,
            ],
            [
                'company_name' => 'Toko Elektronik Jaya',
                'customer_name' => 'Dewi Lestari',
                'phone' => '081456789012',
                'address' => 'Jl. Thamrin No. 88, Jakarta Pusat',
                'is_active' => true,
            ],
            [
                'company_name' => 'PT Logistik Nusantara',
                'customer_name' => 'Rudi Hartono',
                'phone' => '081567890123',
                'address' => 'Jl. MT Haryono No. 67, Jakarta Timur',
                'is_active' => true,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::create($customer);
        }
    }
}
