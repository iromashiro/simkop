<?php

namespace App\Services\Financial;

use App\Models\Financial\FinancialReport;
use App\Models\Cooperative;
use Illuminate\Support\Facades\Log;

class YearOverYearComparisonService
{
    /**
     * Compare financial reports across multiple years.
     */
    public function compareAcrossYears(int $cooperativeId, array $years, string $reportType): array
    {
        try {
            $reports = FinancialReport::where('cooperative_id', $cooperativeId)
                ->where('report_type', $reportType)
                ->whereIn('reporting_year', $years)
                ->where('status', 'approved')
                ->with($this->getRelationshipForReportType($reportType))
                ->orderBy('reporting_year')
                ->get();

            if ($reports->isEmpty()) {
                throw new \Exception('No approved reports found for the specified years.');
            }

            $comparison = [
                'cooperative_id' => $cooperativeId,
                'report_type' => $reportType,
                'years' => $years,
                'yearly_data' => [],
                'trends' => [],
                'growth_rates' => [],
                'variance_analysis' => [],
                'summary' => []
            ];

            // Extract yearly data
            foreach ($reports as $report) {
                $comparison['yearly_data'][$report->reporting_year] = $this->extractReportData($report);
            }

            // Calculate trends and growth rates
            $comparison['trends'] = $this->calculateTrends($comparison['yearly_data'], $reportType);
            $comparison['growth_rates'] = $this->calculateGrowthRates($comparison['yearly_data'], $reportType);
            $comparison['variance_analysis'] = $this->performVarianceAnalysis($comparison['yearly_data'], $reportType);
            $comparison['summary'] = $this->generateComparisonSummary($comparison);

            return $comparison;
        } catch (\Exception $e) {
            Log::error('Error in year-over-year comparison', [
                'cooperative_id' => $cooperativeId,
                'years' => $years,
                'report_type' => $reportType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Compare multiple cooperatives for a specific year.
     */
    public function compareCooperatives(array $cooperativeIds, int $year, string $reportType): array
    {
        try {
            $reports = FinancialReport::whereIn('cooperative_id', $cooperativeIds)
                ->where('report_type', $reportType)
                ->where('reporting_year', $year)
                ->where('status', 'approved')
                ->with(['cooperative', $this->getRelationshipForReportType($reportType)])
                ->get();

            if ($reports->isEmpty()) {
                throw new \Exception('No approved reports found for the specified cooperatives and year.');
            }

            $comparison = [
                'year' => $year,
                'report_type' => $reportType,
                'cooperative_data' => [],
                'rankings' => [],
                'benchmarks' => [],
                'peer_analysis' => []
            ];

            // Extract cooperative data
            foreach ($reports as $report) {
                $comparison['cooperative_data'][$report->cooperative_id] = [
                    'cooperative_name' => $report->cooperative->name,
                    'data' => $this->extractReportData($report)
                ];
            }

            // Calculate rankings and benchmarks
            $comparison['rankings'] = $this->calculateRankings($comparison['cooperative_data'], $reportType);
            $comparison['benchmarks'] = $this->calculateBenchmarks($comparison['cooperative_data'], $reportType);
            $comparison['peer_analysis'] = $this->performPeerAnalysis($comparison['cooperative_data'], $reportType);

            return $comparison;
        } catch (\Exception $e) {
            Log::error('Error in cooperative comparison', [
                'cooperative_ids' => $cooperativeIds,
                'year' => $year,
                'report_type' => $reportType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate comprehensive financial dashboard.
     */
    public function generateFinancialDashboard(int $cooperativeId, int $currentYear): array
    {
        try {
            $dashboard = [
                'cooperative_id' => $cooperativeId,
                'current_year' => $currentYear,
                'financial_overview' => [],
                'key_metrics' => [],
                'trend_analysis' => [],
                'performance_indicators' => [],
                'alerts' => []
            ];

            // Get all report types for current year
            $reportTypes = ['balance_sheet', 'income_statement', 'cash_flow', 'equity_changes'];
            $currentYearReports = [];

            foreach ($reportTypes as $reportType) {
                $report = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', $reportType)
                    ->where('reporting_year', $currentYear)
                    ->where('status', 'approved')
                    ->with($this->getRelationshipForReportType($reportType))
                    ->first();

                if ($report) {
                    $currentYearReports[$reportType] = $report;
                }
            }

            // Generate financial overview
            $dashboard['financial_overview'] = $this->generateFinancialOverview($currentYearReports);

            // Calculate key metrics
            $dashboard['key_metrics'] = $this->calculateKeyMetrics($currentYearReports);

            // Analyze trends (3-year comparison)
            $years = [$currentYear - 2, $currentYear - 1, $currentYear];
            foreach ($reportTypes as $reportType) {
                if (isset($currentYearReports[$reportType])) {
                    $trendData = $this->compareAcrossYears($cooperativeId, $years, $reportType);
                    $dashboard['trend_analysis'][$reportType] = $trendData['trends'];
                }
            }

            // Calculate performance indicators
            $dashboard['performance_indicators'] = $this->calculatePerformanceIndicators($currentYearReports);

            // Generate alerts
            $dashboard['alerts'] = $this->generateFinancialAlerts($currentYearReports, $dashboard['key_metrics']);

            return $dashboard;
        } catch (\Exception $e) {
            Log::error('Error generating financial dashboard', [
                'cooperative_id' => $cooperativeId,
                'current_year' => $currentYear,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Extract data from financial report based on type.
     */
    private function extractReportData(FinancialReport $report): array
    {
        switch ($report->report_type) {
            case 'balance_sheet':
                return $this->extractBalanceSheetData($report);
            case 'income_statement':
                return $this->extractIncomeStatementData($report);
            case 'cash_flow':
                return $this->extractCashFlowData($report);
            case 'equity_changes':
                return $this->extractEquityChangesData($report);
            default:
                return [];
        }
    }

    /**
     * Extract balance sheet data.
     */
    private function extractBalanceSheetData(FinancialReport $report): array
    {
        $accounts = $report->balanceSheetAccounts;

        return [
            'total_assets' => $accounts->where('account_category', 'asset')->sum('current_year_amount'),
            'current_assets' => $accounts->where('account_category', 'asset')
                ->where('account_name', 'like', '%lancar%')->sum('current_year_amount'),
            'fixed_assets' => $accounts->where('account_category', 'asset')
                ->where('account_name', 'like', '%tetap%')->sum('current_year_amount'),
            'total_liabilities' => $accounts->where('account_category', 'liability')->sum('current_year_amount'),
            'current_liabilities' => $accounts->where('account_category', 'liability')
                ->where('account_name', 'like', '%lancar%')->sum('current_year_amount'),
            'total_equity' => $accounts->where('account_category', 'equity')->sum('current_year_amount'),
            'cash_and_equivalents' => $accounts->where('account_category', 'asset')
                ->where('account_name', 'like', '%kas%')->sum('current_year_amount')
        ];
    }

    /**
     * Extract income statement data.
     */
    private function extractIncomeStatementData(FinancialReport $report): array
    {
        $accounts = $report->incomeStatementAccounts;

        $totalRevenue = $accounts->where('account_category', 'revenue')->sum('current_year_amount');
        $totalExpenses = $accounts->where('account_category', 'expense')->sum('current_year_amount');

        return [
            'total_revenue' => $totalRevenue,
            'operating_revenue' => $accounts->where('account_category', 'revenue')
                ->where('account_name', 'not like', '%lain%')->sum('current_year_amount'),
            'other_income' => $accounts->where('account_category', 'other_income')->sum('current_year_amount'),
            'total_expenses' => $totalExpenses,
            'operating_expenses' => $accounts->where('account_category', 'expense')
                ->where('account_name', 'not like', '%lain%')->sum('current_year_amount'),
            'other_expenses' => $accounts->where('account_category', 'other_expense')->sum('current_year_amount'),
            'net_income' => $totalRevenue - $totalExpenses,
            'gross_profit' => $totalRevenue - $accounts->where('account_category', 'expense')
                ->where('account_name', 'like', '%pokok%')->sum('current_year_amount')
        ];
    }

    /**
     * Extract cash flow data.
     */
    private function extractCashFlowData(FinancialReport $report): array
    {
        $activities = $report->cashFlowActivities;

        return [
            'operating_cash_flow' => $activities->where('activity_category', 'operating')->sum('current_year_amount'),
            'investing_cash_flow' => $activities->where('activity_category', 'investing')->sum('current_year_amount'),
            'financing_cash_flow' => $activities->where('activity_category', 'financing')->sum('current_year_amount'),
            'net_cash_flow' => $activities->sum('current_year_amount'),
            'beginning_cash' => $report->data['beginning_cash_balance'] ?? 0,
            'ending_cash' => $report->data['ending_cash_balance'] ?? 0
        ];
    }

    /**
     * Extract equity changes data.
     */
    private function extractEquityChangesData(FinancialReport $report): array
    {
        $equityChanges = $report->equityChanges;

        return [
            'total_beginning_balance' => $equityChanges->sum('beginning_balance'),
            'total_additions' => $equityChanges->sum('additions'),
            'total_reductions' => $equityChanges->sum('reductions'),
            'total_ending_balance' => $equityChanges->sum('ending_balance'),
            'net_change' => $equityChanges->sum('ending_balance') - $equityChanges->sum('beginning_balance'),
            'member_equity' => $equityChanges->whereIn(
                'equity_component',
                ['simpanan_pokok', 'simpanan_wajib', 'simpanan_sukarela']
            )->sum('ending_balance'),
            'retained_earnings' => $equityChanges->whereIn(
                'equity_component',
                ['cadangan', 'shu_belum_dibagi', 'laba_ditahan']
            )->sum('ending_balance')
        ];
    }

    /**
     * Calculate trends across years.
     */
    private function calculateTrends(array $yearlyData, string $reportType): array
    {
        $trends = [];
        $years = array_keys($yearlyData);
        sort($years);

        if (count($years) < 2) {
            return $trends;
        }

        $metrics = $this->getMetricsForReportType($reportType);

        foreach ($metrics as $metric) {
            $values = [];
            foreach ($years as $year) {
                if (isset($yearlyData[$year][$metric])) {
                    $values[$year] = $yearlyData[$year][$metric];
                }
            }

            if (count($values) >= 2) {
                $trends[$metric] = $this->calculateTrendDirection($values);
            }
        }

        return $trends;
    }

    /**
     * Calculate growth rates.
     */
    private function calculateGrowthRates(array $yearlyData, string $reportType): array
    {
        $growthRates = [];
        $years = array_keys($yearlyData);
        sort($years);

        if (count($years) < 2) {
            return $growthRates;
        }

        $metrics = $this->getMetricsForReportType($reportType);

        for ($i = 1; $i < count($years); $i++) {
            $currentYear = $years[$i];
            $previousYear = $years[$i - 1];

            $growthRates[$currentYear] = [];

            foreach ($metrics as $metric) {
                $currentValue = $yearlyData[$currentYear][$metric] ?? 0;
                $previousValue = $yearlyData[$previousYear][$metric] ?? 0;

                if ($previousValue != 0) {
                    $growthRate = (($currentValue - $previousValue) / abs($previousValue)) * 100;
                    $growthRates[$currentYear][$metric] = round($growthRate, 2);
                } else {
                    $growthRates[$currentYear][$metric] = $currentValue > 0 ? 100 : 0;
                }
            }
        }

        return $growthRates;
    }

    /**
     * Perform variance analysis.
     */
    private function performVarianceAnalysis(array $yearlyData, string $reportType): array
    {
        $variance = [];
        $metrics = $this->getMetricsForReportType($reportType);

        foreach ($metrics as $metric) {
            $values = [];
            foreach ($yearlyData as $year => $data) {
                if (isset($data[$metric])) {
                    $values[] = $data[$metric];
                }
            }

            if (count($values) >= 2) {
                $mean = array_sum($values) / count($values);
                $varianceValue = array_sum(array_map(function ($x) use ($mean) {
                    return pow($x - $mean, 2);
                }, $values)) / count($values);

                $standardDeviation = sqrt($varianceValue);
                $coefficientOfVariation = $mean != 0 ? ($standardDeviation / abs($mean)) * 100 : 0;

                $variance[$metric] = [
                    'mean' => round($mean, 2),
                    'standard_deviation' => round($standardDeviation, 2),
                    'coefficient_of_variation' => round($coefficientOfVariation, 2),
                    'volatility' => $this->classifyVolatility($coefficientOfVariation)
                ];
            }
        }

        return $variance;
    }

    /**
     * Generate comparison summary.
     */
    private function generateComparisonSummary(array $comparison): array
    {
        $summary = [
            'period_covered' => count($comparison['yearly_data']) . ' years',
            'key_insights' => [],
            'performance_highlights' => [],
            'areas_of_concern' => []
        ];

        // Analyze trends for key insights
        foreach ($comparison['trends'] as $metric => $trend) {
            if ($trend['direction'] === 'increasing' && $trend['strength'] === 'strong') {
                $summary['performance_highlights'][] = ucfirst(str_replace('_', ' ', $metric)) . ' shows strong growth';
            } elseif ($trend['direction'] === 'decreasing' && $trend['strength'] === 'strong') {
                $summary['areas_of_concern'][] = ucfirst(str_replace('_', ' ', $metric)) . ' is declining significantly';
            }
        }

        // Analyze variance for stability insights
        foreach ($comparison['variance_analysis'] as $metric => $variance) {
            if ($variance['volatility'] === 'high') {
                $summary['areas_of_concern'][] = ucfirst(str_replace('_', ' ', $metric)) . ' shows high volatility';
            } elseif ($variance['volatility'] === 'low') {
                $summary['performance_highlights'][] = ucfirst(str_replace('_', ' ', $metric)) . ' is stable';
            }
        }

        return $summary;
    }

    /**
     * Calculate rankings among cooperatives.
     */
    private function calculateRankings(array $cooperativeData, string $reportType): array
    {
        $rankings = [];
        $metrics = $this->getMetricsForReportType($reportType);

        foreach ($metrics as $metric) {
            $metricData = [];
            foreach ($cooperativeData as $cooperativeId => $data) {
                if (isset($data['data'][$metric])) {
                    $metricData[$cooperativeId] = [
                        'name' => $data['cooperative_name'],
                        'value' => $data['data'][$metric]
                    ];
                }
            }

            // Sort by value (descending for most metrics)
            $isDescending = !in_array($metric, ['total_expenses', 'total_liabilities', 'current_liabilities']);
            if ($isDescending) {
                arsort($metricData);
            } else {
                asort($metricData);
            }

            $rank = 1;
            foreach ($metricData as $cooperativeId => $data) {
                $rankings[$metric][$cooperativeId] = [
                    'rank' => $rank++,
                    'value' => $data['value'],
                    'name' => $data['name']
                ];
            }
        }

        return $rankings;
    }

    /**
     * Calculate benchmarks.
     */
    private function calculateBenchmarks(array $cooperativeData, string $reportType): array
    {
        $benchmarks = [];
        $metrics = $this->getMetricsForReportType($reportType);

        foreach ($metrics as $metric) {
            $values = [];
            foreach ($cooperativeData as $data) {
                if (isset($data['data'][$metric])) {
                    $values[] = $data['data'][$metric];
                }
            }

            if (!empty($values)) {
                sort($values);
                $count = count($values);

                $benchmarks[$metric] = [
                    'minimum' => min($values),
                    'maximum' => max($values),
                    'average' => array_sum($values) / $count,
                    'median' => $count % 2 === 0 ?
                        ($values[$count / 2 - 1] + $values[$count / 2]) / 2 :
                        $values[floor($count / 2)],
                    'percentile_25' => $values[floor($count * 0.25)],
                    'percentile_75' => $values[floor($count * 0.75)]
                ];
            }
        }

        return $benchmarks;
    }

    /**
     * Perform peer analysis.
     */
    private function performPeerAnalysis(array $cooperativeData, string $reportType): array
    {
        $peerAnalysis = [];
        $benchmarks = $this->calculateBenchmarks($cooperativeData, $reportType);

        foreach ($cooperativeData as $cooperativeId => $data) {
            $analysis = [
                'cooperative_name' => $data['cooperative_name'],
                'performance_vs_peers' => []
            ];

            foreach ($benchmarks as $metric => $benchmark) {
                if (isset($data['data'][$metric])) {
                    $value = $data['data'][$metric];
                    $percentileRank = $this->calculatePercentileRank($value, $cooperativeData, $metric);

                    $analysis['performance_vs_peers'][$metric] = [
                        'value' => $value,
                        'vs_average' => $value - $benchmark['average'],
                        'vs_median' => $value - $benchmark['median'],
                        'percentile_rank' => $percentileRank,
                        'performance_level' => $this->classifyPerformance($percentileRank)
                    ];
                }
            }

            $peerAnalysis[$cooperativeId] = $analysis;
        }

        return $peerAnalysis;
    }

    /**
     * Generate financial overview.
     */
    private function generateFinancialOverview(array $reports): array
    {
        $overview = [];

        if (isset($reports['balance_sheet'])) {
            $balanceSheetData = $this->extractBalanceSheetData($reports['balance_sheet']);
            $overview['balance_sheet'] = [
                'total_assets' => $balanceSheetData['total_assets'],
                'total_liabilities' => $balanceSheetData['total_liabilities'],
                'total_equity' => $balanceSheetData['total_equity'],
                'debt_to_equity_ratio' => $balanceSheetData['total_equity'] > 0 ?
                    $balanceSheetData['total_liabilities'] / $balanceSheetData['total_equity'] : 0
            ];
        }

        if (isset($reports['income_statement'])) {
            $incomeData = $this->extractIncomeStatementData($reports['income_statement']);
            $overview['income_statement'] = [
                'total_revenue' => $incomeData['total_revenue'],
                'total_expenses' => $incomeData['total_expenses'],
                'net_income' => $incomeData['net_income'],
                'profit_margin' => $incomeData['total_revenue'] > 0 ?
                    ($incomeData['net_income'] / $incomeData['total_revenue']) * 100 : 0
            ];
        }

        if (isset($reports['cash_flow'])) {
            $cashFlowData = $this->extractCashFlowData($reports['cash_flow']);
            $overview['cash_flow'] = [
                'operating_cash_flow' => $cashFlowData['operating_cash_flow'],
                'net_cash_flow' => $cashFlowData['net_cash_flow'],
                'ending_cash' => $cashFlowData['ending_cash']
            ];
        }

        return $overview;
    }

    /**
     * Calculate key financial metrics.
     */
    private function calculateKeyMetrics(array $reports): array
    {
        $metrics = [];

        if (isset($reports['balance_sheet']) && isset($reports['income_statement'])) {
            $balanceSheetData = $this->extractBalanceSheetData($reports['balance_sheet']);
            $incomeData = $this->extractIncomeStatementData($reports['income_statement']);

            $metrics['liquidity'] = [
                'current_ratio' => $balanceSheetData['current_liabilities'] > 0 ?
                    $balanceSheetData['current_assets'] / $balanceSheetData['current_liabilities'] : 0,
                'cash_ratio' => $balanceSheetData['current_liabilities'] > 0 ?
                    $balanceSheetData['cash_and_equivalents'] / $balanceSheetData['current_liabilities'] : 0
            ];

            $metrics['profitability'] = [
                'return_on_assets' => $balanceSheetData['total_assets'] > 0 ?
                    ($incomeData['net_income'] / $balanceSheetData['total_assets']) * 100 : 0,
                'return_on_equity' => $balanceSheetData['total_equity'] > 0 ?
                    ($incomeData['net_income'] / $balanceSheetData['total_equity']) * 100 : 0,
                'profit_margin' => $incomeData['total_revenue'] > 0 ?
                    ($incomeData['net_income'] / $incomeData['total_revenue']) * 100 : 0
            ];

            $metrics['leverage'] = [
                'debt_to_equity' => $balanceSheetData['total_equity'] > 0 ?
                    $balanceSheetData['total_liabilities'] / $balanceSheetData['total_equity'] : 0,
                'equity_ratio' => $balanceSheetData['total_assets'] > 0 ?
                    ($balanceSheetData['total_equity'] / $balanceSheetData['total_assets']) * 100 : 0
            ];
        }

        return $metrics;
    }

    /**
     * Calculate performance indicators.
     */
    private function calculatePerformanceIndicators(array $reports): array
    {
        $indicators = [];

        if (isset($reports['income_statement'])) {
            $incomeData = $this->extractIncomeStatementData($reports['income_statement']);

            $indicators['revenue_growth'] = 'stable'; // Would need previous year data
            $indicators['profitability'] = $incomeData['net_income'] > 0 ? 'profitable' : 'loss';
            $indicators['expense_control'] = $incomeData['total_revenue'] > 0 &&
                ($incomeData['total_expenses'] / $incomeData['total_revenue']) < 0.8 ? 'good' : 'needs_attention';
        }

        if (isset($reports['cash_flow'])) {
            $cashFlowData = $this->extractCashFlowData($reports['cash_flow']);

            $indicators['cash_generation'] = $cashFlowData['operating_cash_flow'] > 0 ? 'positive' : 'negative';
            $indicators['liquidity'] = $cashFlowData['ending_cash'] > 0 ? 'adequate' : 'tight';
        }

        return $indicators;
    }

    /**
     * Generate financial alerts.
     */
    private function generateFinancialAlerts(array $reports, array $keyMetrics): array
    {
        $alerts = [];

        // Liquidity alerts
        if (isset($keyMetrics['liquidity']['current_ratio'])) {
            if ($keyMetrics['liquidity']['current_ratio'] < 1) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'liquidity',
                    'message' => 'Current ratio below 1.0 indicates potential liquidity issues'
                ];
            }
        }

        // Profitability alerts
        if (isset($keyMetrics['profitability']['return_on_equity'])) {
            if ($keyMetrics['profitability']['return_on_equity'] < 0) {
                $alerts[] = [
                    'type' => 'error',
                    'category' => 'profitability',
                    'message' => 'Negative return on equity indicates losses'
                ];
            }
        }

        // Leverage alerts
        if (isset($keyMetrics['leverage']['debt_to_equity'])) {
            if ($keyMetrics['leverage']['debt_to_equity'] > 2) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'leverage',
                    'message' => 'High debt-to-equity ratio may indicate financial risk'
                ];
            }
        }

        return $alerts;
    }

    /**
     * Get relationship name for report type.
     */
    private function getRelationshipForReportType(string $reportType): string
    {
        return match ($reportType) {
            'balance_sheet' => 'balanceSheetAccounts',
            'income_statement' => 'incomeStatementAccounts',
            'cash_flow' => 'cashFlowActivities',
            'equity_changes' => 'equityChanges',
            default => 'cooperative'
        };
    }

    /**
     * Get metrics for report type.
     */
    private function getMetricsForReportType(string $reportType): array
    {
        return match ($reportType) {
            'balance_sheet' => ['total_assets', 'total_liabilities', 'total_equity', 'current_assets', 'current_liabilities'],
            'income_statement' => ['total_revenue', 'total_expenses', 'net_income', 'operating_revenue', 'operating_expenses'],
            'cash_flow' => ['operating_cash_flow', 'investing_cash_flow', 'financing_cash_flow', 'net_cash_flow'],
            'equity_changes' => ['total_beginning_balance', 'total_ending_balance', 'net_change', 'member_equity'],
            default => []
        };
    }

    /**
     * Calculate trend direction.
     */
    private function calculateTrendDirection(array $values): array
    {
        $years = array_keys($values);
        $amounts = array_values($values);

        if (count($amounts) < 2) {
            return ['direction' => 'insufficient_data', 'strength' => 'none'];
        }

        // Simple linear regression
        $n = count($amounts);
        $sumX = array_sum($years);
        $sumY = array_sum($amounts);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $years[$i] * $amounts[$i];
            $sumX2 += $years[$i] * $years[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        $direction = $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'stable');
        $strength = abs($slope) > 1000000 ? 'strong' : (abs($slope) > 100000 ? 'moderate' : 'weak');

        return ['direction' => $direction, 'strength' => $strength, 'slope' => $slope];
    }

    /**
     * Classify volatility based on coefficient of variation.
     */
    private function classifyVolatility(float $coefficientOfVariation): string
    {
        if ($coefficientOfVariation < 10) {
            return 'low';
        } elseif ($coefficientOfVariation < 25) {
            return 'medium';
        } else {
            return 'high';
        }
    }

    /**
     * Calculate percentile rank.
     */
    private function calculatePercentileRank(float $value, array $cooperativeData, string $metric): float
    {
        $values = [];
        foreach ($cooperativeData as $data) {
            if (isset($data['data'][$metric])) {
                $values[] = $data['data'][$metric];
            }
        }

        if (empty($values)) {
            return 50;
        }

        sort($values);
        $count = count($values);
        $rank = 0;

        foreach ($values as $v) {
            if ($v < $value) {
                $rank++;
            } elseif ($v == $value) {
                $rank += 0.5;
            }
        }

        return ($rank / $count) * 100;
    }

    /**
     * Classify performance based on percentile rank.
     */
    private function classifyPerformance(float $percentileRank): string
    {
        if ($percentileRank >= 75) {
            return 'excellent';
        } elseif ($percentileRank >= 50) {
            return 'above_average';
        } elseif ($percentileRank >= 25) {
            return 'below_average';
        } else {
            return 'poor';
        }
    }
}
