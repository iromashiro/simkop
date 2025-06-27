<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Master data first
            CooperativeSeeder::class,
            UserSeeder::class,
            RolePermissionSeeder::class,

            // 2. Chart of Accounts
            AccountSeeder::class,

            // 3. Members and their data
            MemberSeeder::class,
            SavingsSeeder::class,
            LoanSeeder::class,

            // 4. Financial transactions
            JournalEntrySeeder::class,

            // 5. SHU and Budget
            ShuPlanSeeder::class,
            BudgetSeeder::class,
        ]);
    }
}
