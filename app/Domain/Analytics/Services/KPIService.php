<?php
// app/Domain/Analytics/Services/KPIService.php
namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Models\KPIMetric;
use App\Domain\Financial\Models\JournalEntry;
use App\Domain\Member\Models\Member;
use App\Domain\Member\Models\Loan;
use App\Domain\Member\Models\Savings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class KPIService
{
    /**
     * ✅ FIXED: Get KPI trends with comprehensive analysis
     */
    public function getKPITrends(int $cooperativeId, string $kpiName, int $periods = 12): array
    {
        $cacheKey = "kpi_trends:{$cooperativeId}:{$kpiName}:{$periods}:" . now()->format('Y-m-d');

        return Cache::remember($cacheKey, 3600, function () use ($cooperativeId, $kpiName, $periods) {
            $trends = [];

            for ($i = $periods - 1; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $periodKey = $date->format('Y-m');

                $value = $this->calculateKPIForPeriod($cooperativeId, $kpiName, $date);

                $trends[] = [
                    'period' => $periodKey,
                    'date' => $date->toDateString(),
                    'value' => $value,
                    'formatted_value' => $this->formatKPIValue($kpiName, $value),
                ];
            }

            return $this->analyzeTrends($trends, $kpiName);
        });
    }

    /**
     * Calculate KPI value for specific period
     */
    private function calculateKPIForPeriod(int $cooperativeId, string $kpiName, Carbon $date): float
    {
        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        switch ($kpiName) {
            case 'total_assets':
                return $this->calculateTotalAssetsForPeriod($cooperativeId, $endDate);
            case 'total_liabilities':
                return $this->calculateTotalLiabilitiesForPeriod($cooperativeId, $endDate);
            case 'total_equity':
                return $this->calculateTotalEquityForPeriod($cooperativeId, $endDate);
            case 'net_income':
                return $this->calculateNetIncomeForPeriod($cooperativeId, $startDate, $endDate);
            case 'roa':
                return $this->calculateROAForPeriod($cooperativeId, $startDate, $endDate);
            case 'roe':
                return $this->calculateROEForPeriod($cooperativeId, $startDate, $endDate);
            case 'total_members':
                return $this->calculateTotalMembersForPeriod($cooperativeId, $endDate);
            case 'active_members':
                return $this->calculateActiveMembersForPeriod($cooperativeId, $endDate);
            case 'member_growth_rate':
                return $this->calculateMemberGrowthRateForPeriod($cooperativeId, $startDate, $endDate);
            case 'total_loans':
                return $this->calculateTotalLoansForPeriod($cooperativeId, $endDate);
            case 'outstanding_loans':
                return $this->calculateOutstandingLoansForPeriod($cooperativeId, $endDate);
            case 'loan_default_rate':
                return $this->calculateLoanDefaultRateForPeriod($cooperativeId, $endDate);
            case 'total_savings':
                return $this->calculateTotalSavingsForPeriod($cooperativeId, $endDate);
            case 'savings_growth_rate':
                return $this->calculateSavingsGrowthRateForPeriod($cooperativeId, $startDate, $endDate);
            case 'cost_to_income_ratio':
                return $this->calculateCostToIncomeRatioForPeriod($cooperativeId, $startDate, $endDate);
            default:
                return 0;
        }
    }

    /**
     * ✅ FIXED: Comprehensive trend analysis
     */
    private function analyzeTrends(array $trends, string $kpiName): array
    {
        $values = array_column($trends, 'value');

        return [
            'data' => $trends,
            'trend_direction' => $this->calculateTrendDirection($values),
            'growth_rate' => $this->calculateGrowthRate($values),
            'volatility' => $this->calculateVolatility($values),
            'moving_average' => $this->calculateMovingAverage($values, 3),
            'seasonal_pattern' => $this->detectSeasonalPattern($trends),
            'forecast' => $this->generateForecast($values, 3),
            'performance_rating' => $this->calculatePerformanceRating($kpiName, $values),
            'insights' => $this->generateInsights($kpiName, $trends),
        ];
    }

    /**
     * Calculate trend direction
     */
    private function calculateTrendDirection(array $values): string
    {
        if (count($values) < 2) {
            return 'insufficient_data';
        }

        $firstHalf = array_slice($values, 0, ceil(count($values) / 2));
        $secondHalf = array_slice($values, floor(count($values) / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        $change = $firstAvg > 0 ? (($secondAvg - $firstAvg) / $firstAvg) * 100 : 0;

        if ($change > 5) return 'strongly_increasing';
        if ($change > 1) return 'increasing';
        if ($change > -1) return 'stable';
        if ($change > -5) return 'decreasing';
        return 'strongly_decreasing';
    }

    /**
     * Calculate growth rate
     */
    private function calculateGrowthRate(array $values): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $firstValue = reset($values);
        $lastValue = end($values);

        if ($firstValue == 0) {
            return 0;
        }

        return (($lastValue - $firstValue) / $firstValue) * 100;
    }

    /**
     * Calculate volatility (standard deviation)
     */
    private function calculateVolatility(array $values): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);

        $variance = array_sum($squaredDifferences) / count($values);
        return sqrt($variance);
    }

    /**
     * Calculate moving average
     */
    private function calculateMovingAverage(array $values, int $window): array
    {
        $movingAverage = [];

        for ($i = 0; $i < count($values); $i++) {
            $start = max(0, $i - $window + 1);
            $subset = array_slice($values, $start, $i - $start + 1);
            $movingAverage[] = array_sum($subset) / count($subset);
        }

        return $movingAverage;
    }

    /**
     * Detect seasonal patterns
     */
    private function detectSeasonalPattern(array $trends): array
    {
        if (count($trends) < 12) {
            return ['pattern' => 'insufficient_data'];
        }

        $monthlyAverages = [];

        foreach ($trends as $trend) {
            $month = (int) substr($trend['period'], -2);
            if (!isset($monthlyAverages[$month])) {
                $monthlyAverages[$month] = [];
            }
            $monthlyAverages[$month][] = $trend['value'];
        }

        $seasonalData = [];
        foreach ($monthlyAverages as $month => $values) {
            $seasonalData[$month] = array_sum($values) / count($values);
        }

        $overallAverage = array_sum($seasonalData) / count($seasonalData);
        $seasonalIndices = [];

        foreach ($seasonalData as $month => $average) {
            $seasonalIndices[$month] = $overallAverage > 0 ? ($average / $overallAverage) : 1;
        }

        return [
            'pattern' => $this->identifySeasonalPattern($seasonalIndices),
            'indices' => $seasonalIndices,
            'peak_months' => $this->findPeakMonths($seasonalIndices),
            'low_months' => $this->findLowMonths($seasonalIndices),
        ];
    }

    /**
     * Generate forecast using linear regression
     */
    private function generateForecast(array $values, int $periods): array
    {
        if (count($values) < 3) {
            return [];
        }

        $n = count($values);
        $x = range(1, $n);
        $y = $values;

        // Calculate linear regression coefficients
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumXX += $x[$i] * $x[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Generate forecast
        $forecast = [];
        for ($i = 1; $i <= $periods; $i++) {
            $nextX = $n + $i;
            $forecastValue = $slope * $nextX + $intercept;
            $forecast[] = [
                'period' => $i,
                'value' => max(0, $forecastValue), // Ensure non-negative values
                'confidence' => $this->calculateForecastConfidence($values, $i),
            ];
        }

        return $forecast;
    }

    /**
     * Calculate performance rating
     */
    private function calculatePerformanceRating(string $kpiName, array $values): string
    {
        if (empty($values)) {
            return 'no_data';
        }

        $latestValue = end($values);
        $growthRate = $this->calculateGrowthRate($values);
        $volatility = $this->calculateVolatility($values);

        // KPI-specific performance criteria
        $criteria = $this->getKPIPerformanceCriteria($kpiName);

        $score = 0;

        // Growth rate scoring
        if ($growthRate >= $criteria['excellent_growth']) {
            $score += 40;
        } elseif ($growthRate >= $criteria['good_growth']) {
            $score += 30;
        } elseif ($growthRate >= $criteria['fair_growth']) {
            $score += 20;
        } else {
            $score += 10;
        }

        // Volatility scoring (lower is better)
        if ($volatility <= $criteria['low_volatility']) {
            $score += 30;
        } elseif ($volatility <= $criteria['medium_volatility']) {
            $score += 20;
        } else {
            $score += 10;
        }

        // Absolute value scoring
        if ($latestValue >= $criteria['excellent_value']) {
            $score += 30;
        } elseif ($latestValue >= $criteria['good_value']) {
            $score += 20;
        } elseif ($latestValue >= $criteria['fair_value']) {
            $score += 10;
        }

        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }

    /**
     * Generate insights based on trend analysis
     */
    private function generateInsights(string $kpiName, array $trends): array
    {
        $insights = [];
        $values = array_column($trends, 'value');

        if (empty($values)) {
            return ['No data available for analysis'];
        }

        $growthRate = $this->calculateGrowthRate($values);
        $trendDirection = $this->calculateTrendDirection($values);
        $volatility = $this->calculateVolatility($values);

        // Growth insights
        if ($growthRate > 10) {
            $insights[] = "Strong growth of " . number_format($growthRate, 2) . "% indicates excellent performance";
        } elseif ($growthRate < -10) {
            $insights[] = "Declining trend of " . number_format($growthRate, 2) . "% requires immediate attention";
        }

        // Volatility insights
        $avgValue = array_sum($values) / count($values);
        $volatilityPercent = $avgValue > 0 ? ($volatility / $avgValue) * 100 : 0;

        if ($volatilityPercent > 30) {
            $insights[] = "High volatility (" . number_format($volatilityPercent, 2) . "%) suggests unstable performance";
        } elseif ($volatilityPercent < 10) {
            $insights[] = "Low volatility indicates stable and predictable performance";
        }

        // Trend insights
        switch ($trendDirection) {
            case 'strongly_increasing':
                $insights[] = "Consistently improving trend shows positive momentum";
                break;
            case 'strongly_decreasing':
                $insights[] = "Consistently declining trend requires strategic intervention";
                break;
            case 'stable':
                $insights[] = "Stable performance indicates consistent operations";
                break;
        }

        // KPI-specific insights
        $kpiInsights = $this->getKPISpecificInsights($kpiName, $values);
        $insights = array_merge($insights, $kpiInsights);

        return $insights;
    }

    /**
     * Get KPI-specific performance criteria
     */
    private function getKPIPerformanceCriteria(string $kpiName): array
    {
        $criteria = [
            'total_assets' => [
                'excellent_growth' => 15,
                'good_growth' => 10,
                'fair_growth' => 5,
                'excellent_value' => 10000000,
                'good_value' => 5000000,
                'fair_value' => 1000000,
                'low_volatility' => 500000,
                'medium_volatility' => 1000000,
            ],
            'roa' => [
                'excellent_growth' => 2,
                'good_growth' => 1,
                'fair_growth' => 0,
                'excellent_value' => 15,
                'good_value' => 10,
                'fair_value' => 5,
                'low_volatility' => 2,
                'medium_volatility' => 5,
            ],
            'roe' => [
                'excellent_growth' => 3,
                'good_growth' => 2,
                'fair_growth' => 0,
                'excellent_value' => 20,
                'good_value' => 15,
                'fair_value' => 10,
                'low_volatility' => 3,
                'medium_volatility' => 7,
            ],
            'loan_default_rate' => [
                'excellent_growth' => -2, // Negative growth is good for default rate
                'good_growth' => -1,
                'fair_growth' => 0,
                'excellent_value' => 2,
                'good_value' => 5,
                'fair_value' => 10,
                'low_volatility' => 1,
                'medium_volatility' => 3,
            ],
        ];

        return $criteria[$kpiName] ?? [
            'excellent_growth' => 10,
            'good_growth' => 5,
            'fair_growth' => 0,
            'excellent_value' => 1000000,
            'good_value' => 500000,
            'fair_value' => 100000,
            'low_volatility' => 50000,
            'medium_volatility' => 100000,
        ];
    }

    /**
     * Get KPI-specific insights
     */
    private function getKPISpecificInsights(string $kpiName, array $values): array
    {
        $insights = [];
        $latestValue = end($values);

        switch ($kpiName) {
            case 'loan_default_rate':
                if ($latestValue > 10) {
                    $insights[] = "High default rate indicates need for improved credit assessment";
                } elseif ($latestValue < 2) {
                    $insights[] = "Excellent loan portfolio quality with low default rate";
                }
                break;

            case 'member_growth_rate':
                if ($latestValue > 20) {
                    $insights[] = "Rapid member growth may require scaling of operations";
                } elseif ($latestValue < 0) {
                    $insights[] = "Member decline suggests need for retention strategies";
                }
                break;

            case 'cost_to_income_ratio':
                if ($latestValue > 80) {
                    $insights[] = "High cost ratio indicates operational inefficiency";
                } elseif ($latestValue < 50) {
                    $insights[] = "Excellent operational efficiency with low cost ratio";
                }
                break;
        }

        return $insights;
    }

    /**
     * Calculate KPIs for current period
     */
    public function calculateKPIs(int $cooperativeId, string $period = 'monthly'): array
    {
        $cacheKey = "kpis:{$cooperativeId}:{$period}:" . now()->format('Y-m-d-H');

        return Cache::remember($cacheKey, 3600, function () use ($cooperativeId, $period) {
            $kpis = [];

            // Financial KPIs
            $kpis['total_assets'] = $this->calculateTotalAssets($cooperativeId);
            $kpis['total_liabilities'] = $this->calculateTotalLiabilities($cooperativeId);
            $kpis['total_equity'] = $this->calculateTotalEquity($cooperativeId);
            $kpis['net_income'] = $this->calculateNetIncome($cooperativeId, $period);
            $kpis['roa'] = $this->calculateROA($cooperativeId, $period);
            $kpis['roe'] = $this->calculateROE($cooperativeId, $period);

            // Member KPIs
            $kpis['total_members'] = $this->calculateTotalMembers($cooperativeId);
            $kpis['active_members'] = $this->calculateActiveMembers($cooperativeId);
            $kpis['member_growth_rate'] = $this->calculateMemberGrowthRate($cooperativeId, $period);

            // Loan KPIs
            $kpis['total_loans'] = $this->calculateTotalLoans($cooperativeId);
            $kpis['outstanding_loans'] = $this->calculateOutstandingLoans($cooperativeId);
            $kpis['loan_default_rate'] = $this->calculateLoanDefaultRate($cooperativeId);
            $kpis['loan_portfolio_quality'] = $this->calculateLoanPortfolioQuality($cooperativeId);

            // Savings KPIs
            $kpis['total_savings'] = $this->calculateTotalSavings($cooperativeId);
            $kpis['average_savings_per_member'] = $this->calculateAverageSavingsPerMember($cooperativeId);
            $kpis['savings_growth_rate'] = $this->calculateSavingsGrowthRate($cooperativeId, $period);

            // Operational KPIs
            $kpis['cost_to_income_ratio'] = $this->calculateCostToIncomeRatio($cooperativeId, $period);
            $kpis['operational_efficiency'] = $this->calculateOperationalEfficiency($cooperativeId, $period);

            return $kpis;
        });
    }

    /**
     * Get KPI Summary
     */
    public function getKPISummary(int $cooperativeId, string $period = 'monthly'): array
    {
        $kpis = $this->calculateKPIs($cooperativeId, $period);

        return [
            'financial_health' => [
                'total_assets' => $kpis['total_assets'],
                'total_equity' => $kpis['total_equity'],
                'roa' => $kpis['roa'],
                'roe' => $kpis['roe'],
            ],
            'member_metrics' => [
                'total_members' => $kpis['total_members'],
                'active_members' => $kpis['active_members'],
                'growth_rate' => $kpis['member_growth_rate'],
            ],
            'loan_portfolio' => [
                'total_loans' => $kpis['total_loans'],
                'outstanding_loans' => $kpis['outstanding_loans'],
                'default_rate' => $kpis['loan_default_rate'],
                'portfolio_quality' => $kpis['loan_portfolio_quality'],
            ],
            'savings_performance' => [
                'total_savings' => $kpis['total_savings'],
                'average_per_member' => $kpis['average_savings_per_member'],
                'growth_rate' => $kpis['savings_growth_rate'],
            ],
        ];
    }

    // ================= PERIOD-SPECIFIC CALCULATIONS =================

    private function calculateTotalAssetsForPeriod(int $cooperativeId, Carbon $date): float
    {
        return DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'ASSET')
            ->where('journal_entries.transaction_date', '<=', $date)
            ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount'));
    }

    private function calculateTotalLiabilitiesForPeriod(int $cooperativeId, Carbon $date): float
    {
        return DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'LIABILITY')
            ->where('journal_entries.transaction_date', '<=', $date)
            ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount'));
    }

    private function calculateTotalEquityForPeriod(int $cooperativeId, Carbon $date): float
    {
        return DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'EQUITY')
            ->where('journal_entries.transaction_date', '<=', $date)
            ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount'));
    }

    private function calculateNetIncomeForPeriod(int $cooperativeId, Carbon $startDate, Carbon $endDate): float
    {
        $revenue = DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'REVENUE')
            ->whereBetween('journal_entries.transaction_date', [$startDate, $endDate])
            ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount'));

        $expenses = DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'EXPENSE')
            ->whereBetween('journal_entries.transaction_date', [$startDate, $endDate])
            ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount'));

        return $revenue - $expenses;
    }

    private function calculateROAForPeriod(int $cooperativeId, Carbon $startDate, Carbon $endDate): float
    {
        $netIncome = $this->calculateNetIncomeForPeriod($cooperativeId, $startDate, $endDate);
        $totalAssets = $this->calculateTotalAssetsForPeriod($cooperativeId, $endDate);

        return $totalAssets > 0 ? ($netIncome / $totalAssets) * 100 : 0;
    }

    private function calculateROEForPeriod(int $cooperativeId, Carbon $startDate, Carbon $endDate): float
    {
        $netIncome = $this->calculateNetIncomeForPeriod($cooperativeId, $startDate, $endDate);
        $totalEquity = $this->calculateTotalEquityForPeriod($cooperativeId, $endDate);

        return $totalEquity > 0 ? ($netIncome / $totalEquity) * 100 : 0;
    }

    private function calculateTotalMembersForPeriod(int $cooperativeId, Carbon $date): int
    {
        return Member::where('cooperative_id', $cooperativeId)
            ->where('created_at', '<=', $date)
            ->count();
    }

    private function calculateActiveMembersForPeriod(int $cooperativeId, Carbon $date): int
    {
        return Member::where('cooperative_id', $cooperativeId)
            ->where('created_at', '<=', $date)
            ->where('status', 'active')
            ->count();
    }

    private function calculateMemberGrowthRateForPeriod(int $cooperativeId, Carbon $startDate, Carbon $endDate): float
    {
        $previousStartDate = $startDate->copy()->subMonth();
        $previousEndDate = $endDate->copy()->subMonth();

        $currentMembers = Member::where('cooperative_id', $cooperativeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $previousMembers = Member::where('cooperative_id', $cooperativeId)
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->count();

        return $previousMembers > 0 ? (($currentMembers - $previousMembers) / $previousMembers) * 100 : 0;
    }

    private function calculateTotalLoansForPeriod(int $cooperativeId, Carbon $date): float
    {
        return Loan::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->where('created_at', '<=', $date)->sum('principal_amount');
    }

    private function calculateOutstandingLoansForPeriod(int $cooperativeId, Carbon $date): float
    {
        return Loan::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->where('created_at', '<=', $date)
            ->where('status', 'active')
            ->sum('outstanding_balance');
    }

    private function calculateLoanDefaultRateForPeriod(int $cooperativeId, Carbon $date): float
    {
        $totalLoans = Loan::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->where('created_at', '<=', $date)->count();

        $defaultedLoans = Loan::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->where('created_at', '<=', $date)
            ->where('status', 'defaulted')
            ->count();

        return $totalLoans > 0 ? ($defaultedLoans / $totalLoans) * 100 : 0;
    }

    private function calculateTotalSavingsForPeriod(int $cooperativeId, Carbon $date): float
    {
        return Savings::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->where('updated_at', '<=', $date)->sum('balance');
    }

    private function calculateSavingsGrowthRateForPeriod(int $cooperativeId, Carbon $startDate, Carbon $endDate): float
    {
        $previousStartDate = $startDate->copy()->subMonth();
        $previousEndDate = $endDate->copy()->subMonth();

        $currentSavings = Savings::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->whereBetween('updated_at', [$startDate, $endDate])->sum('balance');

        $previousSavings = Savings::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->whereBetween('updated_at', [$previousStartDate, $previousEndDate])->sum('balance');

        return $previousSavings > 0 ? (($currentSavings - $previousSavings) / $previousSavings) * 100 : 0;
    }

    private function calculateCostToIncomeRatioForPeriod(int $cooperativeId, Carbon $startDate, Carbon $endDate): float
    {
        $revenue = DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'REVENUE')
            ->whereBetween('journal_entries.transaction_date', [$startDate, $endDate])
            ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount'));

        $expenses = DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'EXPENSE')
            ->whereBetween('journal_entries.transaction_date', [$startDate, $endDate])
            ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount'));

        return $revenue > 0 ? ($expenses / $revenue) * 100 : 0;
    }

    // ================= CURRENT PERIOD CALCULATIONS =================

    private function calculateTotalAssets(int $cooperativeId): float
    {
        return DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'ASSET')
            ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount'));
    }

    private function calculateTotalLiabilities(int $cooperativeId): float
    {
        return DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'LIABILITY')
            ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount'));
    }

    private function calculateTotalEquity(int $cooperativeId): float
    {
        return DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'EQUITY')
            ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount'));
    }

    private function calculateNetIncome(int $cooperativeId, string $period): float
    {
        $dateRange = $this->getPeriodDateRange($period);

        $revenue = DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'REVENUE')
            ->whereBetween('journal_entries.transaction_date', $dateRange)
            ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount'));

        $expenses = DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'EXPENSE')
            ->whereBetween('journal_entries.transaction_date', $dateRange)
            ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount'));

        return $revenue - $expenses;
    }

    private function calculateROA(int $cooperativeId, string $period): float
    {
        $netIncome = $this->calculateNetIncome($cooperativeId, $period);
        $totalAssets = $this->calculateTotalAssets($cooperativeId);

        return $totalAssets > 0 ? ($netIncome / $totalAssets) * 100 : 0;
    }

    private function calculateROE(int $cooperativeId, string $period): float
    {
        $netIncome = $this->calculateNetIncome($cooperativeId, $period);
        $totalEquity = $this->calculateTotalEquity($cooperativeId);

        return $totalEquity > 0 ? ($netIncome / $totalEquity) * 100 : 0;
    }

    private function calculateTotalMembers(int $cooperativeId): int
    {
        return Member::where('cooperative_id', $cooperativeId)->count();
    }

    private function calculateActiveMembers(int $cooperativeId): int
    {
        return Member::where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->count();
    }

    private function calculateMemberGrowthRate(int $cooperativeId, string $period): float
    {
        $dateRange = $this->getPeriodDateRange($period);
        $previousDateRange = $this->getPreviousPeriodDateRange($period);

        $currentMembers = Member::where('cooperative_id', $cooperativeId)
            ->whereBetween('created_at', $dateRange)
            ->count();

        $previousMembers = Member::where('cooperative_id', $cooperativeId)
            ->whereBetween('created_at', $previousDateRange)
            ->count();

        return $previousMembers > 0 ? (($currentMembers - $previousMembers) / $previousMembers) * 100 : 0;
    }

    private function calculateTotalLoans(int $cooperativeId): float
    {
        return Loan::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->sum('principal_amount');
    }

    private function calculateOutstandingLoans(int $cooperativeId): float
    {
        return Loan::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->where('status', 'active')->sum('outstanding_balance');
    }

    private function calculateLoanDefaultRate(int $cooperativeId): float
    {
        $totalLoans = Loan::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->count();

        $defaultedLoans = Loan::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->where('status', 'defaulted')->count();

        return $totalLoans > 0 ? ($defaultedLoans / $totalLoans) * 100 : 0;
    }

    private function calculateLoanPortfolioQuality(int $cooperativeId): string
    {
        $defaultRate = $this->calculateLoanDefaultRate($cooperativeId);

        if ($defaultRate < 2) return 'excellent';
        if ($defaultRate < 5) return 'good';
        if ($defaultRate < 10) return 'fair';
        return 'poor';
    }

    private function calculateTotalSavings(int $cooperativeId): float
    {
        return Savings::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->sum('balance');
    }

    private function calculateAverageSavingsPerMember(int $cooperativeId): float
    {
        $totalSavings = $this->calculateTotalSavings($cooperativeId);
        $totalMembers = $this->calculateTotalMembers($cooperativeId);

        return $totalMembers > 0 ? $totalSavings / $totalMembers : 0;
    }

    private function calculateSavingsGrowthRate(int $cooperativeId, string $period): float
    {
        $dateRange = $this->getPeriodDateRange($period);
        $previousDateRange = $this->getPreviousPeriodDateRange($period);

        $currentSavings = Savings::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->whereBetween('updated_at', $dateRange)->sum('balance');

        $previousSavings = Savings::whereHas('member', function ($query) use ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        })->whereBetween('updated_at', $previousDateRange)->sum('balance');

        return $previousSavings > 0 ? (($currentSavings - $previousSavings) / $previousSavings) * 100 : 0;
    }

    private function calculateCostToIncomeRatio(int $cooperativeId, string $period): float
    {
        $dateRange = $this->getPeriodDateRange($period);

        $revenue = DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'REVENUE')
            ->whereBetween('journal_entries.transaction_date', $dateRange)
            ->sum(DB::raw('journal_lines.credit_amount - journal_lines.debit_amount'));

        $expenses = DB::table('accounts')
            ->join('journal_lines', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'EXPENSE')
            ->whereBetween('journal_entries.transaction_date', $dateRange)
            ->sum(DB::raw('journal_lines.debit_amount - journal_lines.credit_amount'));

        return $revenue > 0 ? ($expenses / $revenue) * 100 : 0;
    }

    private function calculateOperationalEfficiency(int $cooperativeId, string $period): float
    {
        $costToIncomeRatio = $this->calculateCostToIncomeRatio($cooperativeId, $period);
        return 100 - $costToIncomeRatio;
    }

    // ================= HELPER METHODS =================

    private function formatKPIValue(string $kpiName, float $value): string
    {
        $currencyKPIs = ['total_assets', 'total_liabilities', 'total_equity', 'net_income', 'total_loans', 'outstanding_loans', 'total_savings'];
        $percentageKPIs = ['roa', 'roe', 'loan_default_rate', 'member_growth_rate', 'savings_growth_rate', 'cost_to_income_ratio'];

        if (in_array($kpiName, $currencyKPIs)) {
            return 'Rp ' . number_format($value, 0, ',', '.');
        } elseif (in_array($kpiName, $percentageKPIs)) {
            return number_format($value, 2) . '%';
        } else {
            return number_format($value, 0);
        }
    }

    private function identifySeasonalPattern(array $seasonalIndices): string
    {
        $maxIndex = max($seasonalIndices);
        $minIndex = min($seasonalIndices);
        $variance = $maxIndex - $minIndex;

        if ($variance < 0.1) return 'no_seasonality';
        if ($variance < 0.3) return 'low_seasonality';
        if ($variance < 0.5) return 'moderate_seasonality';
        return 'high_seasonality';
    }

    private function findPeakMonths(array $seasonalIndices): array
    {
        $threshold = array_sum($seasonalIndices) / count($seasonalIndices) * 1.1;
        return array_keys(array_filter($seasonalIndices, fn($index) => $index >= $threshold));
    }

    private function findLowMonths(array $seasonalIndices): array
    {
        $threshold = array_sum($seasonalIndices) / count($seasonalIndices) * 0.9;
        return array_keys(array_filter($seasonalIndices, fn($index) => $index <= $threshold));
    }

    private function calculateForecastConfidence(array $values, int $period): float
    {
        $baseConfidence = 90;
        $decayRate = 10; // Confidence decreases by 10% per period
        return max(50, $baseConfidence - ($period * $decayRate));
    }

    private function getPeriodDateRange(string $period): array
    {
        switch ($period) {
            case 'daily':
                return [now()->startOfDay(), now()->endOfDay()];
            case 'weekly':
                return [now()->startOfWeek(), now()->endOfWeek()];
            case 'monthly':
                return [now()->startOfMonth(), now()->endOfMonth()];
            case 'quarterly':
                return [now()->startOfQuarter(), now()->endOfQuarter()];
            case 'yearly':
                return [now()->startOfYear(), now()->endOfYear()];
            default:
                return [now()->startOfMonth(), now()->endOfMonth()];
        }
    }

    private function getPreviousPeriodDateRange(string $period): array
    {
        switch ($period) {
            case 'daily':
                return [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()];
            case 'weekly':
                return [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()];
            case 'monthly':
                return [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()];
            case 'quarterly':
                return [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()];
            case 'yearly':
                return [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()];
            default:
                return [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()];
        }
    }
}
