<?php
// app/Domain/Analytics/Providers/MemberGrowthProvider.php
namespace App\Domain\Analytics\Providers;

use App\Domain\Analytics\Contracts\AnalyticsProviderInterface;
use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;
use App\Domain\Member\Models\Member;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Member Growth Analytics Provider
 * SRS Reference: Section 3.6.5 - Member Analytics
 */
class MemberGrowthProvider implements AnalyticsProviderInterface
{
    public function generate(AnalyticsRequestDTO $request): array
    {
        $dateRange = $request->getDateRange();

        return [
            'total_members' => $this->getTotalMembers($request->cooperativeId),
            'active_members' => $this->getActiveMembers($request->cooperativeId),
            'new_members' => $this->getNewMembers($request->cooperativeId, $dateRange),
            'member_growth_rate' => $this->calculateGrowthRate($request->cooperativeId, $dateRange),
            'member_demographics' => $this->getMemberDemographics($request->cooperativeId),
            'member_activity' => $this->getMemberActivity($request->cooperativeId, $dateRange),
            'retention_rate' => $this->calculateRetentionRate($request->cooperativeId),
            'churn_analysis' => $this->getChurnAnalysis($request->cooperativeId, $dateRange),
        ];
    }

    public function getName(): string
    {
        return 'Member Growth';
    }

    public function getDescription(): string
    {
        return 'Member growth analytics including demographics, activity, and retention metrics';
    }

    public function getRequiredPermissions(): array
    {
        return ['view_members', 'view_member_statistics'];
    }

    public function getCacheKey(AnalyticsRequestDTO $request): string
    {
        return "member_growth:{$request->cooperativeId}:{$request->period}";
    }

    public function getCacheTTL(): int
    {
        return 3600; // 1 hour
    }

    public function validate(AnalyticsRequestDTO $request): bool
    {
        return $request->cooperativeId > 0;
    }

    public function getSupportedMetrics(): array
    {
        return [
            'total_members',
            'active_members',
            'growth_rate',
            'retention_rate',
            'churn_rate'
        ];
    }

    public function supportsRealTime(): bool
    {
        return true;
    }

    public function getConfiguration(): array
    {
        return [
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            'real_time' => true,
            'supported_periods' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']
        ];
    }

    /**
     * Get total members
     */
    private function getTotalMembers(int $cooperativeId): int
    {
        return Member::where('cooperative_id', $cooperativeId)->count();
    }

    /**
     * Get active members
     */
    private function getActiveMembers(int $cooperativeId): int
    {
        return Member::where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->count();
    }

    /**
     * Get new members in date range
     */
    private function getNewMembers(int $cooperativeId, array $dateRange): array
    {
        return Member::where('cooperative_id', $cooperativeId)
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Calculate member growth rate
     */
    private function calculateGrowthRate(int $cooperativeId, array $dateRange): float
    {
        $currentPeriodMembers = Member::where('cooperative_id', $cooperativeId)
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->count();

        $previousPeriodStart = $dateRange['from']->copy()->sub($dateRange['to']->diff($dateRange['from']));
        $previousPeriodMembers = Member::where('cooperative_id', $cooperativeId)
            ->whereBetween('created_at', [$previousPeriodStart, $dateRange['from']])
            ->count();

        if ($previousPeriodMembers == 0) {
            return $currentPeriodMembers > 0 ? 100 : 0;
        }

        return (($currentPeriodMembers - $previousPeriodMembers) / $previousPeriodMembers) * 100;
    }

    /**
     * Get member demographics
     */
    private function getMemberDemographics(int $cooperativeId): array
    {
        $ageGroups = Member::where('cooperative_id', $cooperativeId)
            ->selectRaw('
                CASE
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 25 THEN "Under 25"
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 25 AND 35 THEN "25-35"
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 36 AND 50 THEN "36-50"
                    ELSE "Over 50"
                END as age_group,
                COUNT(*) as count
            ')
            ->groupBy('age_group')
            ->get()
            ->toArray();

        $genderDistribution = Member::where('cooperative_id', $cooperativeId)
            ->selectRaw('gender, COUNT(*) as count')
            ->groupBy('gender')
            ->get()
            ->toArray();

        return [
            'age_groups' => $ageGroups,
            'gender_distribution' => $genderDistribution,
        ];
    }

    /**
     * Get member activity
     */
    private function getMemberActivity(int $cooperativeId, array $dateRange): array
    {
        // Implementation for member activity tracking
        return [
            'active_this_month' => 0,
            'transactions_per_member' => 0,
            'average_session_duration' => 0,
        ];
    }

    /**
     * Calculate retention rate
     */
    private function calculateRetentionRate(int $cooperativeId): float
    {
        $totalMembers = $this->getTotalMembers($cooperativeId);
        $activeMembers = $this->getActiveMembers($cooperativeId);

        return $totalMembers > 0 ? ($activeMembers / $totalMembers) * 100 : 0;
    }

    /**
     * Get churn analysis
     */
    private function getChurnAnalysis(int $cooperativeId, array $dateRange): array
    {
        $churnedMembers = Member::where('cooperative_id', $cooperativeId)
            ->where('status', 'inactive')
            ->whereBetween('updated_at', [$dateRange['from'], $dateRange['to']])
            ->count();

        $totalMembers = $this->getTotalMembers($cooperativeId);

        return [
            'churned_members' => $churnedMembers,
            'churn_rate' => $totalMembers > 0 ? ($churnedMembers / $totalMembers) * 100 : 0,
            'churn_reasons' => [], // Implementation needed
        ];
    }
}
