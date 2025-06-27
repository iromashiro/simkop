<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\Cooperative;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $cooperatives = Cooperative::all();

        // Standard Chart of Accounts untuk Koperasi Indonesia
        $accounts = [
            // ASET (1000-1999)
            ['code' => '1100', 'name' => 'Kas', 'type' => 'asset', 'parent_code' => null],
            ['code' => '1110', 'name' => 'Kas Kecil', 'type' => 'asset', 'parent_code' => '1100'],
            ['code' => '1120', 'name' => 'Kas Besar', 'type' => 'asset', 'parent_code' => '1100'],

            ['code' => '1200', 'name' => 'Bank', 'type' => 'asset', 'parent_code' => null],
            ['code' => '1210', 'name' => 'Bank BRI', 'type' => 'asset', 'parent_code' => '1200'],
            ['code' => '1220', 'name' => 'Bank Mandiri', 'type' => 'asset', 'parent_code' => '1200'],

            ['code' => '1300', 'name' => 'Piutang', 'type' => 'asset', 'parent_code' => null],
            ['code' => '1310', 'name' => 'Piutang Pinjaman Anggota', 'type' => 'asset', 'parent_code' => '1300'],
            ['code' => '1320', 'name' => 'Piutang Bunga', 'type' => 'asset', 'parent_code' => '1300'],
            ['code' => '1330', 'name' => 'Cadangan Kerugian Piutang', 'type' => 'asset', 'parent_code' => '1300'],

            ['code' => '1400', 'name' => 'Persediaan', 'type' => 'asset', 'parent_code' => null],
            ['code' => '1410', 'name' => 'Persediaan Barang Dagangan', 'type' => 'asset', 'parent_code' => '1400'],

            ['code' => '1500', 'name' => 'Aset Tetap', 'type' => 'asset', 'parent_code' => null],
            ['code' => '1510', 'name' => 'Tanah', 'type' => 'asset', 'parent_code' => '1500'],
            ['code' => '1520', 'name' => 'Bangunan', 'type' => 'asset', 'parent_code' => '1500'],
            ['code' => '1521', 'name' => 'Akumulasi Penyusutan Bangunan', 'type' => 'asset', 'parent_code' => '1520'],
            ['code' => '1530', 'name' => 'Kendaraan', 'type' => 'asset', 'parent_code' => '1500'],
            ['code' => '1531', 'name' => 'Akumulasi Penyusutan Kendaraan', 'type' => 'asset', 'parent_code' => '1530'],
            ['code' => '1540', 'name' => 'Peralatan Kantor', 'type' => 'asset', 'parent_code' => '1500'],
            ['code' => '1541', 'name' => 'Akumulasi Penyusutan Peralatan', 'type' => 'asset', 'parent_code' => '1540'],

            // KEWAJIBAN (2000-2999)
            ['code' => '2100', 'name' => 'Kewajiban Lancar', 'type' => 'liability', 'parent_code' => null],
            ['code' => '2110', 'name' => 'Hutang Usaha', 'type' => 'liability', 'parent_code' => '2100'],
            ['code' => '2120', 'name' => 'Hutang Pajak', 'type' => 'liability', 'parent_code' => '2100'],
            ['code' => '2130', 'name' => 'Hutang Gaji', 'type' => 'liability', 'parent_code' => '2100'],

            ['code' => '2200', 'name' => 'Simpanan Anggota', 'type' => 'liability', 'parent_code' => null],
            ['code' => '2210', 'name' => 'Simpanan Pokok', 'type' => 'liability', 'parent_code' => '2200'],
            ['code' => '2220', 'name' => 'Simpanan Wajib', 'type' => 'liability', 'parent_code' => '2200'],
            ['code' => '2230', 'name' => 'Simpanan Khusus', 'type' => 'liability', 'parent_code' => '2200'],
            ['code' => '2240', 'name' => 'Simpanan Sukarela', 'type' => 'liability', 'parent_code' => '2200'],

            ['code' => '2300', 'name' => 'Kewajiban Jangka Panjang', 'type' => 'liability', 'parent_code' => null],
            ['code' => '2310', 'name' => 'Hutang Bank', 'type' => 'liability', 'parent_code' => '2300'],

            // EKUITAS (3000-3999)
            ['code' => '3100', 'name' => 'Modal', 'type' => 'equity', 'parent_code' => null],
            ['code' => '3110', 'name' => 'Modal Awal', 'type' => 'equity', 'parent_code' => '3100'],
            ['code' => '3120', 'name' => 'Cadangan Umum', 'type' => 'equity', 'parent_code' => '3100'],
            ['code' => '3130', 'name' => 'Cadangan Khusus', 'type' => 'equity', 'parent_code' => '3100'],
            ['code' => '3140', 'name' => 'SHU Tahun Berjalan', 'type' => 'equity', 'parent_code' => '3100'],
            ['code' => '3150', 'name' => 'SHU Tahun Lalu', 'type' => 'equity', 'parent_code' => '3100'],

            // PENDAPATAN (4000-4999)
            ['code' => '4100', 'name' => 'Pendapatan Operasional', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '4110', 'name' => 'Pendapatan Bunga Pinjaman', 'type' => 'revenue', 'parent_code' => '4100'],
            ['code' => '4120', 'name' => 'Pendapatan Administrasi', 'type' => 'revenue', 'parent_code' => '4100'],
            ['code' => '4130', 'name' => 'Pendapatan Provisi', 'type' => 'revenue', 'parent_code' => '4100'],

            ['code' => '4200', 'name' => 'Pendapatan Non-Operasional', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '4210', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue', 'parent_code' => '4200'],

            // BEBAN (5000-5999)
            ['code' => '5100', 'name' => 'Beban Operasional', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5110', 'name' => 'Beban Gaji', 'type' => 'expense', 'parent_code' => '5100'],
            ['code' => '5120', 'name' => 'Beban Listrik', 'type' => 'expense', 'parent_code' => '5100'],
            ['code' => '5130', 'name' => 'Beban Telepon', 'type' => 'expense', 'parent_code' => '5100'],
            ['code' => '5140', 'name' => 'Beban ATK', 'type' => 'expense', 'parent_code' => '5100'],
            ['code' => '5150', 'name' => 'Beban Penyusutan', 'type' => 'expense', 'parent_code' => '5100'],
            ['code' => '5160', 'name' => 'Beban Kerugian Piutang', 'type' => 'expense', 'parent_code' => '5100'],

            ['code' => '5200', 'name' => 'Beban Non-Operasional', 'type' => 'expense', 'parent_code' => null],
            ['code' => '5210', 'name' => 'Beban Bunga Bank', 'type' => 'expense', 'parent_code' => '5200'],
            ['code' => '5220', 'name' => 'Beban Lain-lain', 'type' => 'expense', 'parent_code' => '5200'],
        ];

        // Create accounts for each cooperative
        foreach ($cooperatives as $cooperative) {
            foreach ($accounts as $account) {
                Account::create([
                    'cooperative_id' => $cooperative->id,
                    'code' => $account['code'],
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'parent_code' => $account['parent_code'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
