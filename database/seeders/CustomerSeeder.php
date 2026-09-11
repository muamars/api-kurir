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
           
        ];

        foreach ($customers as $customer) {
            Customer::create($customer);
        }
    }
}
