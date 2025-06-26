<?php
// app/Domain/Analytics/Providers/MemberGrowthProvider.php
namespace App\Domain\Analytics\Providers;

use App\Domain\Analytics\Contracts\AnalyticsProviderInterface;
use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;
use App\Domain\Analytics\DTOs\WidgetDataDTO;
use Illuminate\Support\Facades\DB;

/**
 * Member Growth Analytics Provider
 */
class MemberGrowthProvider implements AnalyticsProviderInterface
{
    public function generate(AnalyticsRequestDTO $request): WidgetDataDTO
    {
        $dateRange = $this->getDateRange($request->period);

        // Get member growth data
        $growthData = $this->getMemberGrowthData($request->cooperativeId, $dateRange);

        // Get member demographics
        $demographics = $this->getMemberDemographics($request->cooperativeId);

        // Get member activity metrics
        $activityMetrics = $this->getMemberActivityMetrics($request->cooperativeId, $dateRange);

        return new WidgetDataDTO(
            type: 'member_growth',
            title: 'Member Growth Analysis',
            data: [
                'growth_data' => $growthData,
                'demographics' => $demographics,
                'activity_metrics' => $activityMetrics,
            ],
            chartData: $this->generateChartData($growthData, $demographics),
            summary: $this->generateSummary($growthData, $activityMetrics)
        );
    }

    private function getMemberGrowthData(int $cooperativeId, array $dateRange): array
    {
        // Monthly member growth over the period
        $monthlyGrowth = DB::table('members')
            ->where('cooperative_id', $cooperativeId)
            ->whereBetween('join_date', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('
                DATE_TRUNC(\'month\', join_date) as month,
                COUNT(*) as new_members,
                SUM(COUNT(*)) OVER (ORDER BY DATE_TRUNC(\'month\', join_date)) as cumulative_members
            ')
            ->groupBy(DB::raw('DATE_TRUNC(\'month\', join_date)'))
            ->orderBy('month')
            ->get();

        // Member status distribution
        $statusDistribution = DB::table('members')
            ->where('cooperative_id', $cooperativeId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return [
            'monthly_growth' => $monthlyGrowth,
            'status_distribution' => $statusDistribution,
            'total_members' => $monthlyGrowth->sum('new_members'),
            'growth_rate' => $this->calculateGrowthRate($monthlyGrowth),
        ];
    }

    private function getMemberDemographics(int $cooperativeId): array
    {
        // Age distribution
        $ageDistribution = DB::table('members')
            ->where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->selectRaw('
                CASE
                    WHEN EXTRACT(YEAR FROM AGE(birth_date)) < 25 THEN \'Under 25\'
                    WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 25 AND 35 THEN \'25-35\'
                    WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 36 AND 50 THEN \'36-50\'
                    WHEN EXTRACT(YEAR FROM AGE(birth_date)) > 50 THEN \'Over 50\'
                    ELSE \'Unknown\'
                END as age_group,
                COUNT(*) as count
            ')
            ->groupBy(DB::raw('
                CASE
                    WHEN EXTRACT(YEAR FROM AGE(birth_date)) < 25 THEN \'Under 25\'
                    WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 25 AND 35 THEN \'25-35\'
                    WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 36 AND 50 THEN \'36-50\'
                    WHEN EXTRACT(YEAR FROM AGE(birth_date)) > 50 THEN \'Over 50\'
                    ELSE \'Unknown\'
                END
            '))
            ->get();

        // Gender distribution
        $genderDistribution = DB::table('members')
            ->where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->selectRaw('gender, COUNT(*) as count')
            ->groupBy('gender')
            ->get();

        return [
            'age_distribution' => $ageDistribution,
            'gender_distribution' => $genderDistribution,
        ];
    }

    private function getMemberActivityMetrics(int $cooperativeId, array $dateRange): array
    {
        // Active members (with transactions in period)
        $activeMembers = DB::table('members as m')
            ->join('savings as s', 'm.id', '=', 's.member_id')
            ->where('m.cooperative_id', $cooperativeId)
            ->whereBetween('s.transaction_date', [$dateRange['start'], $dateRange['end']])
            ->distinct('m.id')
            ->count();

        // Total active members
        $totalActiveMembers = DB::table('members')
            ->where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->count();

        // Average transactions per member
        $avgTransactions = DB::table('members as m')
            ->leftJoin('savings as s', function ($join) use ($dateRange) {
                $join->on('m.id', '=', 's.member_id')
                    ->whereBetween('s.transaction_date', [$dateRange['start'], $dateRange['end']]);
            })
            ->where('m.cooperative_id', $cooperativeId)
            ->where('m.status', 'active')
            ->selectRaw('AVG(transaction_count) as avg_transactions')
            ->fromSub(function ($query) use ($dateRange) {
                $query->selectRaw('m.id, COUNT(s.id) as transaction_count')
                    ->from('members as m')
                    ->leftJoin('savings as s', function ($join) use ($dateRange) {
                        $join->on('m.id', '=', 's.member_id')
                            ->whereBetween('s.transaction_date', [$dateRange['start'], $dateRange['end']]);
                    })
                    ->groupBy('m.id');
            }, 'member_transactions')
            ->value('avg_transactions');

        return [
            'active_members' => $activeMembers,
            'total_active_members' => $totalActiveMembers,
            'activity_rate' => $totalActiveMembers > 0 ? ($activeMembers / $totalActiveMembers) * 100 : 0,
            'avg_transactions_per_member' => (float) $avgTransactions,
        ];
    }

    private function calculateGrowthRate(object $monthlyGrowth): float
    {
        if ($monthlyGrowth->count() < 2) {
            return 0;
        }

        $first = $monthlyGrowth->first();
        $last = $monthlyGrowth->last();

        if ($first->cumulative_members == 0) {
            return 100;
        }

        return (($last->cumulative_members - $first->cumulative_members) / $first->cumulative_members) * 100;
    }

    private function generateChartData(array $growthData, array $demographics): array
    {
        return [
            'member_growth_trend' => [
                'categories' => $growthData['monthly_growth']->pluck('month')->map(function ($date) {
                    return date('M Y', strtotime($date));
                })->toArray(),
                'new_members' => $growthData['monthly_growth']->pluck('new_members')->toArray(),
                'cumulative_members' => $growthData['monthly_growth']->pluck('cumulative_members')->toArray(),
            ],
            'age_distribution' => [
                'categories' => $demographics['age_distribution']->pluck('age_group')->toArray(),
                'data' => $demographics['age_distribution']->pluck('count')->toArray(),
            ],
            'gender_distribution' => [
                'categories' => $demographics['gender_distribution']->pluck('gender')->toArray(),
                'data' => $demographics['gender_distribution']->pluck('count')->toArray(),
            ]
        ];
    }

    private function generateSummary(array $growthData, array $activityMetrics): array
    {
        return [
            'total_new_members' => $growthData['total_members'],
            'growth_rate' => number_format($growthData['growth_rate'], 1) . '%',
            'activity_rate' => number_format($activityMetrics['activity_rate'], 1) . '%',
            'avg_transactions' => number_format($activityMetrics['avg_transactions_per_member'], 1),
        ];
    }

    private function getDateRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'monthly' => [
                'start' => $now->subMonths(12)->startOfMonth()->toDateString(),
                'end' => $now->endOfMonth()->toDateString()
            ],
            'quarterly' => [
                'start' => $now->subQuarters(4)->startOfQuarter()->toDateString(),
                'end' => $now->endOfQuarter()->toDateString()
            ],
            'yearly' => [
                'start' => $now->subYears(3)->startOfYear()->toDateString(),
                'end' => $now->endOfYear()->toDateString()
            ],
            default => [
                'start' => $now->subYear()->toDateString(),
                'end' => $now->toDateString()
            ]
        };
    }
}
