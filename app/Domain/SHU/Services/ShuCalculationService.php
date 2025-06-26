<?php
// app/Domain/SHU/Services/ShuCalculationService.php
namespace App\Domain\SHU\Services;

use App\Domain\SHU\Models\ShuPlan;
use App\Domain\SHU\Models\ShuMemberCalculation;
use App\Domain\SHU\DTOs\ShuCalculationDTO;
use App\Domain\SHU\DTOs\ShuDistributionDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * MEMORY OPTIMIZED: SHU Calculation Service with chunk processing
 * SRS Reference: Section 3.5 - SHU Distribution System
 */
class ShuCalculationService
{
    private const CHUNK_SIZE = 500;
    private const BATCH_SIZE = 100;
    private const LOCK_TIMEOUT = 300; // 5 minutes

    /**
     * SECURITY & PERFORMANCE FIX: Thread-safe SHU calculation with memory optimization
     */
    public function calculateShuDistribution(ShuCalculationDTO $dto): ShuDistributionDTO
    {
        $lockKey = "shu_calculation:{$dto->shuPlan->id}";

        return Cache::lock($lockKey, self::LOCK_TIMEOUT)->block(5, function () use ($dto) {
            return DB::transaction(function () use ($dto) {
                try {
                    // Check if calculation is already in progress
                    $shuPlan = ShuPlan::lockForUpdate()->find($dto->shuPlan->id);

                    if ($shuPlan->status === 'calculating') {
                        throw new \Exception('SHU calculation already in progress');
                    }

                    // Mark as calculating
                    $shuPlan->update(['status' => 'calculating']);

                    Log::info('Starting SHU calculation', [
                        'shu_plan_id' => $dto->shuPlan->id,
                        'fiscal_period' => $dto->shuPlan->fiscal_period_id,
                        'total_shu' => $dto->shuPlan->total_shu_amount,
                        'memory_start' => memory_get_usage(true),
                    ]);

                    // PERFORMANCE FIX: Calculate totals first for ratio calculation
                    $totals = $this->calculateParticipationTotals($dto);

                    // MEMORY FIX: Process members in chunks
                    $memberCalculations = [];
                    $processedCount = 0;

                    foreach ($this->getMemberParticipationDataChunked($dto) as $memberChunk) {
                        foreach ($memberChunk as $member) {
                            $ratios = $this->calculateMemberRatios($member, $totals, $dto);
                            $calculation = $this->calculateMemberShu($member, $ratios, $dto);
                            $memberCalculations[] = $calculation;
                            $processedCount++;

                            // Save in batches to prevent memory buildup
                            if (count($memberCalculations) >= self::BATCH_SIZE) {
                                $this->saveShuCalculationsBatch($memberCalculations, $dto->shuPlan);
                                $memberCalculations = [];

                                // Log progress
                                Log::info('SHU calculation progress', [
                                    'processed_members' => $processedCount,
                                    'memory_usage' => memory_get_usage(true),
                                ]);
                            }
                        }
                    }

                    // Save remaining calculations
                    if (!empty($memberCalculations)) {
                        $this->saveShuCalculationsBatch($memberCalculations, $dto->shuPlan);
                    }

                    // Generate final summary
                    $summary = $this->generateDistributionSummary($dto);

                    // Mark as calculated
                    $shuPlan->update(['status' => 'calculated']);

                    Log::info('SHU calculation completed successfully', [
                        'total_members' => $processedCount,
                        'total_distributed' => $summary['total_distributed'],
                        'memory_peak' => memory_get_peak_usage(true),
                    ]);

                    return new ShuDistributionDTO(
                        shuPlan: $dto->shuPlan->fresh(),
                        memberCalculations: [], // Don't load all in memory
                        summary: $summary,
                        calculatedAt: now(),
                        calculatedBy: auth()->user()
                    );
                } catch (\Exception $e) {
                    // Reset status on error
                    $shuPlan->update(['status' => 'draft']);

                    Log::error('SHU calculation failed', [
                        'shu_plan_id' => $dto->shuPlan->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            });
        });
    }

    /**
     * PERFORMANCE FIX: Calculate participation totals first
     */
    private function calculateParticipationTotals(ShuCalculationDTO $dto): array
    {
        $fiscalPeriod = $dto->shuPlan->fiscalPeriod;

        $totals = DB::table('members as m')
            ->leftJoin('savings as s', function ($join) use ($fiscalPeriod) {
                $join->on('m.id', '=', 's.member_id')
                    ->whereBetween('s.transaction_date', [
                        $fiscalPeriod->start_date,
                        $fiscalPeriod->end_date
                    ]);
            })
            ->leftJoin('loans as l', function ($join) use ($fiscalPeriod) {
                $join->on('m.id', '=', 'l.member_id')
                    ->whereBetween('l.disbursement_date', [
                        $fiscalPeriod->start_date,
                        $fiscalPeriod->end_date
                    ])
                    ->whereIn('l.status', ['active', 'completed']);
            })
            ->leftJoin('loan_payments as lp', function ($join) use ($fiscalPeriod) {
                $join->on('l.id', '=', 'lp.loan_id')
                    ->whereBetween('lp.payment_date', [
                        $fiscalPeriod->start_date,
                        $fiscalPeriod->end_date
                    ]);
            })
            ->where('m.cooperative_id', $dto->shuPlan->cooperative_id)
            ->where('m.status', 'active')
            ->where('m.join_date', '<=', $fiscalPeriod->end_date)
            ->selectRaw('
                SUM(CASE WHEN s.type = \'pokok\' THEN COALESCE(s.amount, 0) ELSE 0 END) as total_simpanan_pokok,
                SUM(CASE WHEN s.type = \'wajib\' THEN COALESCE(s.amount, 0) ELSE 0 END) as total_simpanan_wajib,
                SUM(CASE WHEN s.type = \'sukarela\' THEN COALESCE(s.amount, 0) ELSE 0 END) as total_simpanan_sukarela,
                AVG(COALESCE(l.principal_amount, 0)) as total_avg_loan_balance,
                SUM(COALESCE(lp.principal_amount, 0)) as total_loan_payments,
                COUNT(DISTINCT m.id) as total_members,
                SUM(EXTRACT(YEAR FROM AGE(?, m.join_date)) * 12 + EXTRACT(MONTH FROM AGE(?, m.join_date))) as total_membership_months
            ')
            ->addBinding([$fiscalPeriod->end_date, $fiscalPeriod->end_date])
            ->first();

        return [
            'simpanan_pokok' => (float) $totals->total_simpanan_pokok,
            'simpanan_wajib' => (float) $totals->total_simpanan_wajib,
            'simpanan_sukarela' => (float) $totals->total_simpanan_sukarela,
            'avg_loan_balance' => (float) $totals->total_avg_loan_balance,
            'loan_payments' => (float) $totals->total_loan_payments,
            'membership_months' => (float) $totals->total_membership_months,
            'total_members' => (int) $totals->total_members,
        ];
    }

    /**
     * MEMORY FIX: Get member participation data in chunks
     */
    private function getMemberParticipationDataChunked(ShuCalculationDTO $dto): \Generator
    {
        $fiscalPeriod = $dto->shuPlan->fiscalPeriod;

        DB::table('members as m')
            ->leftJoin('savings as s', function ($join) use ($fiscalPeriod) {
                $join->on('m.id', '=', 's.member_id')
                    ->whereBetween('s.transaction_date', [
                        $fiscalPeriod->start_date,
                        $fiscalPeriod->end_date
                    ]);
            })
            ->leftJoin('loans as l', function ($join) use ($fiscalPeriod) {
                $join->on('m.id', '=', 'l.member_id')
                    ->whereBetween('l.disbursement_date', [
                        $fiscalPeriod->start_date,
                        $fiscalPeriod->end_date
                    ])
                    ->whereIn('l.status', ['active', 'completed']);
            })
            ->leftJoin('loan_payments as lp', function ($join) use ($fiscalPeriod) {
                $join->on('l.id', '=', 'lp.loan_id')
                    ->whereBetween('lp.payment_date', [
                        $fiscalPeriod->start_date,
                        $fiscalPeriod->end_date
                    ]);
            })
            ->where('m.cooperative_id', $dto->shuPlan->cooperative_id)
            ->where('m.status', 'active')
            ->where('m.join_date', '<=', $fiscalPeriod->end_date)
            ->select([
                'm.id as member_id',
                'm.member_number',
                'm.name',
                'm.join_date',
                DB::raw('COALESCE(SUM(CASE WHEN s.type = \'pokok\' THEN s.amount ELSE 0 END), 0) as simpanan_pokok'),
                DB::raw('COALESCE(SUM(CASE WHEN s.type = \'wajib\' THEN s.amount ELSE 0 END), 0) as simpanan_wajib'),
                DB::raw('COALESCE(SUM(CASE WHEN s.type = \'sukarela\' THEN s.amount ELSE 0 END), 0) as simpanan_sukarela'),
                DB::raw('COALESCE(AVG(l.principal_amount), 0) as avg_loan_balance'),
                DB::raw('COALESCE(SUM(lp.principal_amount), 0) as total_loan_payments'),
                DB::raw('COUNT(DISTINCT s.id) + COUNT(DISTINCT lp.id) as activity_score'),
                DB::raw('EXTRACT(YEAR FROM AGE(?, m.join_date)) * 12 + EXTRACT(MONTH FROM AGE(?, m.join_date)) as membership_months')
            ])
            ->addBinding([$fiscalPeriod->end_date, $fiscalPeriod->end_date])
            ->groupBy(['m.id', 'm.member_number', 'm.name', 'm.join_date'])
            ->orderBy('m.member_number')
            ->chunk(self::CHUNK_SIZE, function ($members) {
                yield $members;
            });
    }

    /**
     * Calculate distribution ratios for a member
     */
    private function calculateMemberRatios(object $member, array $totals, ShuCalculationDTO $dto): array
    {
        $plan = $dto->shuPlan;
        $ratios = [];

        // Savings-based distribution (Jasa Modal)
        if ($plan->savings_percentage > 0) {
            $memberSavings = $member->simpanan_pokok + $member->simpanan_wajib + $member->simpanan_sukarela;
            $totalSavings = $totals['simpanan_pokok'] + $totals['simpanan_wajib'] + $totals['simpanan_sukarela'];

            $ratios['savings_ratio'] = $totalSavings > 0 ? $memberSavings / $totalSavings : 0;
        }

        // Transaction-based distribution (Jasa Usaha)
        if ($plan->transaction_percentage > 0) {
            $ratios['transaction_ratio'] = $totals['avg_loan_balance'] > 0 ?
                $member->avg_loan_balance / $totals['avg_loan_balance'] : 0;
        }

        // Activity-based distribution
        if ($plan->activity_percentage > 0) {
            $totalActivity = $totals['total_members'] * 10; // Assume average activity
            $ratios['activity_ratio'] = $totalActivity > 0 ?
                $member->activity_score / $totalActivity : 0;
        }

        // Membership duration-based distribution
        if ($plan->membership_percentage > 0) {
            $ratios['membership_ratio'] = $totals['membership_months'] > 0 ?
                $member->membership_months / $totals['membership_months'] : 0;
        }

        return $ratios;
    }

    /**
     * Calculate individual member SHU amounts
     */
    private function calculateMemberShu(object $member, array $ratios, ShuCalculationDTO $dto): array
    {
        $plan = $dto->shuPlan;
        $memberShu = 0;
        $breakdown = [];

        // Calculate each component
        if ($plan->savings_percentage > 0) {
            $savingsAmount = ($plan->total_shu_amount * $plan->savings_percentage / 100) *
                ($ratios['savings_ratio'] ?? 0);
            $memberShu += $savingsAmount;
            $breakdown['savings_shu'] = $savingsAmount;
        }

        if ($plan->transaction_percentage > 0) {
            $transactionAmount = ($plan->total_shu_amount * $plan->transaction_percentage / 100) *
                ($ratios['transaction_ratio'] ?? 0);
            $memberShu += $transactionAmount;
            $breakdown['transaction_shu'] = $transactionAmount;
        }

        if ($plan->activity_percentage > 0) {
            $activityAmount = ($plan->total_shu_amount * $plan->activity_percentage / 100) *
                ($ratios['activity_ratio'] ?? 0);
            $memberShu += $activityAmount;
            $breakdown['activity_shu'] = $activityAmount;
        }

        if ($plan->membership_percentage > 0) {
            $membershipAmount = ($plan->total_shu_amount * $plan->membership_percentage / 100) *
                ($ratios['membership_ratio'] ?? 0);
            $memberShu += $membershipAmount;
            $breakdown['membership_shu'] = $membershipAmount;
        }

        // Apply minimum and maximum limits
        $memberShu = max($memberShu, $plan->minimum_shu_amount ?? 0);
        if ($plan->maximum_shu_amount) {
            $memberShu = min($memberShu, $plan->maximum_shu_amount);
        }

        return [
            'member_id' => $member->member_id,
            'total_shu_amount' => round($memberShu, 2),
            'breakdown' => $breakdown,
            'ratios' => $ratios,
        ];
    }

    /**
     * PERFORMANCE FIX: Save SHU calculations in batches
     */
    private function saveShuCalculationsBatch(array $calculations, ShuPlan $shuPlan): void
    {
        if (empty($calculations)) {
            return;
        }

        $insertData = [];
        foreach ($calculations as $calculation) {
            $insertData[] = [
                'shu_plan_id' => $shuPlan->id,
                'member_id' => $calculation['member_id'],
                'total_shu_amount' => $calculation['total_shu_amount'],
                'savings_shu_amount' => $calculation['breakdown']['savings_shu'] ?? 0,
                'transaction_shu_amount' => $calculation['breakdown']['transaction_shu'] ?? 0,
                'activity_shu_amount' => $calculation['breakdown']['activity_shu'] ?? 0,
                'membership_shu_amount' => $calculation['breakdown']['membership_shu'] ?? 0,
                'calculation_data' => json_encode([
                    'ratios' => $calculation['ratios'],
                    'breakdown' => $calculation['breakdown'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Batch insert for performance
        ShuMemberCalculation::insert($insertData);

        Log::debug('Saved SHU calculation batch', [
            'batch_size' => count($insertData),
            'shu_plan_id' => $shuPlan->id,
        ]);
    }

    /**
     * Generate distribution summary from saved calculations
     */
    private function generateDistributionSummary(ShuCalculationDTO $dto): array
    {
        $summary = ShuMemberCalculation::where('shu_plan_id', $dto->shuPlan->id)
            ->selectRaw('
                COUNT(*) as total_members,
                SUM(total_shu_amount) as total_distributed,
                AVG(total_shu_amount) as average_per_member,
                SUM(savings_shu_amount) as savings_total,
                SUM(transaction_shu_amount) as transaction_total,
                SUM(activity_shu_amount) as activity_total,
                SUM(membership_shu_amount) as membership_total
            ')
            ->first();

        return [
            'total_shu_planned' => $dto->shuPlan->total_shu_amount,
            'total_distributed' => (float) $summary->total_distributed,
            'remaining_amount' => $dto->shuPlan->total_shu_amount - $summary->total_distributed,
            'total_members' => (int) $summary->total_members,
            'average_per_member' => (float) $summary->average_per_member,
            'distribution_breakdown' => [
                'savings_total' => (float) $summary->savings_total,
                'transaction_total' => (float) $summary->transaction_total,
                'activity_total' => (float) $summary->activity_total,
                'membership_total' => (float) $summary->membership_total,
            ],
        ];
    }
}
