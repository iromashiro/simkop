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
                'name' => 'Koperasi Sejahtera Mandiri',
                'kementerian_id' => 'KOP001',
                'registration_number' => '001/KOP/ME/2024',
                'address' => 'Jl. Merdeka No. 123, Muara Enim',
                'phone' => '0734-421234',
                'email' => 'info@sejahteramandiri.com',
                'chairman_name' => 'Budi Santoso',
                'manager_name' => 'Siti Rahayu',
                'establishment_date' => '2020-01-15',
                'business_type' => 'simpan_pinjam',
                'status' => 'active',
                'member_count' => 150,
                'asset_value' => 2500000000, // 2.5 Miliar
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Koperasi Maju Bersama',
                'kementerian_id' => 'KOP002',
                'registration_number' => '002/KOP/ME/2024',
                'address' => 'Jl. Sudirman No. 456, Muara Enim',
                'phone' => '0734-421235',
                'email' => 'admin@majubersama.com',
                'chairman_name' => 'Ahmad Wijaya',
                'manager_name' => 'Dewi Lestari',
                'establishment_date' => '2019-03-20',
                'business_type' => 'konsumen',
                'status' => 'active',
                'member_count' => 200,
                'asset_value' => 1800000000, // 1.8 Miliar
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Koperasi Tani Subur',
                'kementerian_id' => 'KOP003',
                'registration_number' => '003/KOP/ME/2024',
                'address' => 'Jl. Raya Desa No. 789, Muara Enim',
                'phone' => '0734-421236',
                'email' => 'kontak@tanisubur.com',
                'chairman_name' => 'Pak Tani',
                'manager_name' => 'Ibu Sari',
                'establishment_date' => '2018-06-10',
                'business_type' => 'produksi',
                'status' => 'active',
                'member_count' => 75,
                'asset_value' => 950000000, // 950 Juta
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        foreach ($cooperatives as $coop) {
            Cooperative::create($coop);
        }
    }
}
