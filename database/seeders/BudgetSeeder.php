<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Account;
use App\Models\Cooperative;
use Faker\Factory as Faker;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $cooperatives = Cooperative::all();

        foreach ($cooperatives as $cooperative) {
            $accounts = Account::where('cooperative_id', $cooperative->id)->get()->keyBy('code');

            // Budget tahun lalu (sudah selesai)
            $lastYear = now()->subYear()->year;
            $lastYearBudget = Budget::create([
                'cooperative_id' => $cooperative->id,
                'fiscal_year' => $lastYear,
                'name' => "RAPB Koperasi {$cooperative->name} Tahun {$lastYear}",
                'description' => "Rencana Anggaran Pendapatan dan Belanja tahun {$lastYear}",
                'total_revenue_budget' => 500000000,
                'total_expense_budget' => 400000000,
                'total_revenue_actual' => 520000000,
                'total_expense_actual' => 380000000,
                'status' => 'completed',
                'approved_by' => 1,
                'approved_at' => now()->subYear()->startOfYear(),
                'created_at' => now()->subYear()->startOfYear()->subMonths(2),
                'updated_at' => now()->subYear()->endOfYear(),
            ]);

            // Budget items untuk tahun lalu
            $this->createBudgetItems($lastYearBudget, $accounts, $faker, true);

            // Budget tahun ini (aktif)
            $currentYear = now()->year;
            $currentYearBudget = Budget::create([
                'cooperative_id' => $cooperative->id,
                'fiscal_year' => $currentYear,
                'name' => "RAPB Koperasi {$cooperative->name} Tahun {$currentYear}",
                'description' => "Rencana Anggaran Pendapatan dan Belanja tahun {$currentYear}",
                'total_revenue_budget' => 600000000,
                'total_expense_budget' => 480000000,
                'total_revenue_actual' => 0,
                'total_expense_actual' => 0,
                'status' => 'approved',
                'approved_by' => 1,
                'approved_at' => now()->startOfYear(),
                'created_at' => now()->startOfYear()->subMonths(2),
                'updated_at' => now(),
            ]);

            // Budget items untuk tahun ini
            $this->createBudgetItems($currentYearBudget, $accounts, $faker, false);

            // Budget tahun depan (draft)
            $nextYear = now()->addYear()->year;
            $nextYearBudget = Budget::create([
                'cooperative_id' => $cooperative->id,
                'fiscal_year' => $nextYear,
                'name' => "RAPB Koperasi {$cooperative->name} Tahun {$nextYear}",
                'description' => "Rencana Anggaran Pendapatan dan Belanja tahun {$nextYear}",
                'total_revenue_budget' => 700000000,
                'total_expense_budget' => 550000000,
                'total_revenue_actual' => 0,
                'total_expense_actual' => 0,
                'status' => 'draft',
                'approved_by' => null,
                'approved_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Budget items untuk tahun depan
            $this->createBudgetItems($nextYearBudget, $accounts, $faker, false);
        }
    }

    private function createBudgetItems($budget, $accounts, $faker, $hasActual = false)
    {
        // Revenue items
        $revenueItems = [
            ['4110', 'Pendapatan Bunga Pinjaman', 400000000],
            ['4120', 'Pendapatan Administrasi', 80000000],
            ['4130', 'Pendapatan Provisi', 40000000],
            ['4210', 'Pendapatan Lain-lain', 20000000],
        ];

        foreach ($revenueItems as [$accountCode, $description, $budgetAmount]) {
            $actualAmount = $hasActual ?
                $budgetAmount * $faker->randomFloat(2, 0.8, 1.2) : 0;

            BudgetItem::create([
                'budget_id' => $budget->id,
                'account_id' => $accounts[$accountCode]->id,
                'category' => 'revenue',
                'description' => $description,
                'budget_amount' => $budgetAmount,
                'actual_amount' => $actualAmount,
                'variance_amount' => $actualAmount - $budgetAmount,
                'variance_percentage' => $budgetAmount > 0 ?
                    (($actualAmount - $budgetAmount) / $budgetAmount) * 100 : 0,
                'notes' => $hasActual ?
                    ($actualAmount > $budgetAmount ? 'Melebihi target' : 'Di bawah target') :
                    null,
            ]);
        }

        // Expense items
        $expenseItems = [
            ['5110', 'Beban Gaji', 240000000],
            ['5120', 'Beban Listrik', 36000000],
            ['5130', 'Beban Telepon', 12000000],
            ['5140', 'Beban ATK', 15000000],
            ['5150', 'Beban Penyusutan', 50000000],
            ['5160', 'Beban Kerugian Piutang', 20000000],
            ['5210', 'Beban Bunga Bank', 15000000],
            ['5220', 'Beban Lain-lain', 12000000],
        ];

        foreach ($expenseItems as [$accountCode, $description, $budgetAmount]) {
            $actualAmount = $hasActual ?
                $budgetAmount * $faker->randomFloat(2, 0.7, 1.1) : 0;

            BudgetItem::create([
                'budget_id' => $budget->id,
                'account_id' => $accounts[$accountCode]->id,
                'category' => 'expense',
                'description' => $description,
                'budget_amount' => $budgetAmount,
                'actual_amount' => $actualAmount,
                'variance_amount' => $budgetAmount - $actualAmount, // Untuk expense, variance positif = hemat
                'variance_percentage' => $budgetAmount > 0 ?
                    (($budgetAmount - $actualAmount) / $budgetAmount) * 100 : 0,
                'notes' => $hasActual ?
                    ($actualAmount < $budgetAmount ? 'Hemat dari budget' : 'Melebihi budget') :
                    null,
            ]);
        }
    }
}
