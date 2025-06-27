<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Cooperative;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Exception;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        try {
            // Pastikan roles sudah ada sebelum assign
            $this->checkRequiredRoles();

            $cooperatives = Cooperative::all();

            // Super Admin (Dinas Koperasi)
            $superAdmin = User::firstOrCreate(
                ['email' => 'admin@dinaskoperasi.muaraenim.go.id'],
                [
                    'name' => 'Admin Dinas Koperasi',
                    'password' => Hash::make('password123'),
                    'cooperative_id' => null, // Super admin tidak terikat koperasi
                    'phone' => '0734-421000',
                    'position' => 'Kepala Dinas',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            if (!$superAdmin->hasRole('super_admin')) {
                $superAdmin->assignRole('super_admin');
            }

            // Admin untuk setiap koperasi
            foreach ($cooperatives as $index => $cooperative) {
                $cooperativeIndex = $index + 1;

                // Admin Koperasi
                $admin = User::firstOrCreate(
                    ['email' => "admin@koperasi{$cooperativeIndex}.com"],
                    [
                        'name' => "Admin {$cooperative->name}",
                        'password' => Hash::make('password123'),
                        'cooperative_id' => $cooperative->id,
                        'phone' => '0734-42' . str_pad((1100 + $index), 4, '0', STR_PAD_LEFT),
                        'position' => 'Manager',
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]
                );

                if (!$admin->hasRole('cooperative_admin')) {
                    $admin->assignRole('cooperative_admin');
                }

                // Staff Koperasi
                $staff = User::firstOrCreate(
                    ['email' => "staff@koperasi{$cooperativeIndex}.com"],
                    [
                        'name' => "Staff {$cooperative->name}",
                        'password' => Hash::make('password123'),
                        'cooperative_id' => $cooperative->id,
                        'phone' => '0734-42' . str_pad((1200 + $index), 4, '0', STR_PAD_LEFT),
                        'position' => 'Staff Operasional',
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]
                );

                if (!$staff->hasRole('cooperative_staff')) {
                    $staff->assignRole('cooperative_staff');
                }

                // Kasir
                $cashier = User::firstOrCreate(
                    ['email' => "kasir@koperasi{$cooperativeIndex}.com"],
                    [
                        'name' => "Kasir {$cooperative->name}",
                        'password' => Hash::make('password123'),
                        'cooperative_id' => $cooperative->id,
                        'phone' => '0734-42' . str_pad((1300 + $index), 4, '0', STR_PAD_LEFT),
                        'position' => 'Kasir',
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]
                );

                if (!$cashier->hasRole('cooperative_staff')) {
                    $cashier->assignRole('cooperative_staff');
                }

                $this->command->info("Created users for cooperative: {$cooperative->name}");
            }

            $this->command->info('UserSeeder completed successfully!');
        } catch (Exception $e) {
            $this->command->error("Error in UserSeeder: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if required roles exist
     */
    private function checkRequiredRoles(): void
    {
        $requiredRoles = ['super_admin', 'cooperative_admin', 'cooperative_staff'];

        foreach ($requiredRoles as $role) {
            if (!DB::table('roles')->where('name', $role)->exists()) {
                throw new Exception("Role '{$role}' does not exist. Please run RoleSeeder first.");
            }
        }
    }
}
