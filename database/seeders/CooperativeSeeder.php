<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cooperative;

class CooperativeSeeder extends Seeder
{
    public function run(): void
    {
        $cooperatives = [
            [
                'name' => 'KPN Kesatuan Muara Enim',
                'address' => 'Jl. Lintas Sumatera KM 5, Muara Enim, Sumatera Selatan',
                'phone' => '0734-421234',
                'email' => 'kpnkesatuan@email.com',
                'chairman_name' => 'Budi Santoso',
                'registration_number' => 'REG-001-2020',
                'status' => 'active',
            ],
            [
                'name' => 'Koperasi Sejahtera Mandiri',
                'address' => 'Jl. Merdeka No. 15, Muara Enim, Sumatera Selatan',
                'phone' => '0734-421235',
                'email' => 'sejahteramandiri@email.com',
                'chairman_name' => 'Siti Aminah',
                'registration_number' => 'REG-002-2020',
                'status' => 'active',
            ],
            [
                'name' => 'Koperasi Maju Bersama',
                'address' => 'Jl. Sudirman No. 25, Muara Enim, Sumatera Selatan',
                'phone' => '0734-421236',
                'email' => 'majubersama@email.com',
                'chairman_name' => 'Ahmad Wijaya',
                'registration_number' => 'REG-003-2020',
                'status' => 'active',
            ],
        ];

        foreach ($cooperatives as $cooperative) {
            Cooperative::firstOrCreate(
                ['registration_number' => $cooperative['registration_number']],
                $cooperative
            );
        }
    }
}
