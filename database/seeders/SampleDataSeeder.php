<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Cooperative;
use Spatie\Permission\Models\Role;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get roles
        $adminDinasRole = Role::where('name', 'admin_dinas')->first();
        $adminKoperasiRole = Role::where('name', 'admin_koperasi')->first();

        // Create Admin Dinas users
        $adminDinas1 = User::firstOrCreate(
            ['email' => 'admin.dinas@simkop.com'],
            [
                'name' => 'Admin Dinas Koperasi',
                'password' => bcrypt('password'),
                'cooperative_id' => null,
            ]
        );
        $adminDinas1->assignRole($adminDinasRole);

        // Create Admin Koperasi users for each cooperative
        $cooperatives = Cooperative::all();

        foreach ($cooperatives as $index => $cooperative) {
            $adminKoperasi = User::firstOrCreate(
                ['email' => "admin.{$cooperative->registration_number}@simkop.com"],
                [
                    'name' => "Admin {$cooperative->name}",
                    'password' => bcrypt('password'),
                    'cooperative_id' => $cooperative->id,
                ]
            );
            $adminKoperasi->assignRole($adminKoperasiRole);
        }
    }
}
