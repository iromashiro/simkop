<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ShuPlan;
use App\Models\ShuDistribution;
use App\Models\Cooperative;
use App\Models\Member;
use Faker\Factory as Faker;

class ShuPlanSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $cooperatives = Cooperative::all();

        foreach ($cooperatives as $cooperative) {
            // SHU Plan untuk tahun lalu (sudah selesai)
            $lastYear = now()->subYear()->year;
            $lastYearShu = ShuPlan::create([
                'cooperative_id' => $cooperative->id,
                'fiscal_year' => $lastYear,
                'total_shu' => 150000000, // 150 juta
                'member_services_percentage' => 40.0,
                'member_capital_percentage' => 25.0,
                'reserve_fund_percentage' => 20.0,
                'management_percentage' => 10.0,
                'employee_percentage' => 5.0,
                'member_services_amount' => 60000000,
                'member_capital_amount' => 37500000,
                'reserve_fund_amount' => 30000000,
                'management_amount' => 15000000,
                'employee_amount' => 7500000,
                'status' => 'distributed',
                'approved_by' => 1,
                'approved_at' => now()->subYear()->endOfYear(),
                'created_at' => now()->subYear()->endOfYear(),
                'updated_at' => now()->subYear()->endOfYear(),
            ]);

            // Generate distribusi SHU untuk anggota
            $members = Member::where('cooperative_id', $cooperative->id)
                ->where('status', 'active')
                ->get();

            $totalMemberDistribution = $lastYearShu->member_services_amount + $lastYearShu->member_capital_amount;

            foreach ($members as $member) {
                // Hitung berdasarkan transaksi dan simpanan
                $serviceScore = $faker->numberBetween(1, 100); // Simulasi skor jasa
                $capitalScore = $faker->numberBetween(1, 100); // Simulasi skor modal

                $serviceAmount = ($serviceScore / 100) * $lastYearShu->member_services_amount / $members->count();
                $capitalAmount = ($capitalScore / 100) * $lastYearShu->member_capital_amount / $members->count();

                ShuDistribution::create([
                    'shu_plan_id' => $lastYearShu->id,
                    'member_id' => $member->id,
                    'service_amount' => round($serviceAmount),
                    'capital_amount' => round($capitalAmount),
                    'total_amount' => round($serviceAmount + $capitalAmount),
                    'service_score' => $serviceScore,
                    'capital_score' => $capitalScore,
                    'payment_status' => 'paid',
                    'payment_date' => $faker->dateTimeBetween($lastYearShu->approved_at, $lastYearShu->approved_at->format('Y-m-d') . ' +30 days'),
                    'created_at' => $lastYearShu->created_at,
                    'updated_at' => $lastYearShu->updated_at,
                ]);
            }

            // SHU Plan untuk tahun ini (dalam proses)
            $currentYear = now()->year;
            ShuPlan::create([
                'cooperative_id' => $cooperative->id,
                'fiscal_year' => $currentYear,
                'total_shu' => 200000000, // 200 juta (proyeksi)
                'member_services_percentage' => 40.0,
                'member_capital_percentage' => 25.0,
                'reserve_fund_percentage' => 20.0,
                'management_percentage' => 10.0,
                'employee_percentage' => 5.0,
                'member_services_amount' => 80000000,
                'member_capital_amount' => 50000000,
                'reserve_fund_amount' => 40000000,
                'management_amount' => 20000000,
                'employee_amount' => 10000000,
                'status' => 'draft',
                'approved_by' => null,
                'approved_at' => null,
                'created_at' => now()->startOfYear(),
                'updated_at' => now(),
            ]);
        }
    }
}
