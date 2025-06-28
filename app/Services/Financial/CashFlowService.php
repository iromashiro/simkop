<?php

namespace App\Services\Financial;

use App\Models\Financial\FinancialReport;
use App\Models\Financial\CashFlowActivity;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashFlowService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Generate cash flow statement.
     */
    public function generateCashFlowStatement(array $data): FinancialReport
    {
        return DB::transaction(function () use ($data) {
            try {
                // Create the main report
                $report = FinancialReport::create([
                    'cooperative_id' => $data['cooperative_id'],
                    'report_type' => 'cash_flow',
                    'reporting_year' => $data['reporting_year'],
                    'reporting_period' => $data['reporting_period'],
                    'status' => $data['status'] ?? 'draft',
                    'notes' => $data['notes'] ?? null,
                    'data' => $this->prepareCashFlowData($data),
                    'created_by' => auth()->id(),
                ]);

                // Create cash flow activities
                foreach ($data['activities'] as $activityData) {
                    $report->cashFlowActivities()->create([
                        'activity_category' => $activityData['activity_category'],
                        'activity_description' => $activityData['activity_description'],
                        'current_year_amount' => $activityData['current_year_amount'],
                        'previous_year_amount' => $activityData['previous_year_amount'] ?? 0,
                        'note_reference' => $activityData['note_reference'] ?? null,
                        'is_subtotal' => $activityData['is_subtotal'] ?? false,
                        'sort_order' => $activityData['sort_order'] ?? 0,
                    ]);
                }

                // Log the generation
                $this->auditLogService->log(
                    'cash_flow_report_generated',
                    'Cash flow report generated',
                    [
                        'report_id' => $report->id,
                        'cooperative_id' => $report->cooperative_id,
                        'reporting_year' => $report->reporting_year
                    ]
                );

                return $report->load('cashFlowActivities');
            } catch (\Exception $e) {
                Log::error('Error generating cash flow report', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                    'user_id' => auth()->id()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Calculate cash flow from operations using indirect method.
     */
    public function calculateOperatingCashFlowIndirect(int $cooperativeId, int $year): array
    {
        try {
            // Get net income from income statement
            $incomeStatement = FinancialReport::where('cooperative_id', $cooperativeId)
                ->where('report_type', 'income_statement')
                ->where('reporting_year', $year)
                ->where('status', 'approved')
                ->with('incomeStatementAccounts')
                ->first();

            if (!$incomeStatement) {
                throw new \Exception('Income statement not found for the specified year.');
            }

            $netIncome = $this->calculateNetIncome($incomeStatement);

            // Get balance sheet changes
            $balanceSheetChanges = $this->getBalanceSheetChanges($cooperativeId, $year);

            // Calculate operating cash flow adjustments
            $adjustments = $this->calculateOperatingAdjustments($balanceSheetChanges);

            $operatingCashFlow = [
                'net_income' => $netIncome,
                'adjustments' => $adjustments,
                'working_capital_changes' => $this->calculateWorkingCapitalChanges($balanceSheetChanges),
                'total_operating_cash_flow' => $netIncome + array_sum($adjustments) + $this->calculateWorkingCapitalChanges($balanceSheetChanges)['net_change']
            ];

            return $operatingCashFlow;
        } catch (\Exception $e) {
            Log::error('Error calculating operating cash flow', [
                'cooperative_id' => $cooperativeId,
                'year' => $year,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Analyze cash flow patterns and trends.
     */
    public function analyzeCashFlowPatterns(int $cooperativeId, int $year): array
    {
        try {
            $report = FinancialReport::where('cooperative_id', $cooperativeId)
                ->where('report_type', 'cash_flow')
                ->where('reporting_year', $year)
                ->where('status', 'approved')
                ->with('cashFlowActivities')
                ->first();

            if (!$report) {
                throw new \Exception('Cash flow report not found for the specified year.');
            }

            $activities = $report->cashFlowActivities;

            // Calculate cash flows by category
            $operatingCashFlow = $activities->where('activity_category', 'operating')
                ->where('is_subtotal', false)
                ->sum('current_year_amount');

            $investingCashFlow = $activities->where('activity_category', 'investing')
                ->where('is_subtotal', false)
                ->sum('current_year_amount');

            $financingCashFlow = $activities->where('activity_category', 'financing')
                ->where('is_subtotal', false)
                ->sum('current_year_amount');

            $netCashFlow = $operatingCashFlow + $investingCashFlow + $financingCashFlow;

            // Analyze cash flow pattern
            $pattern = $this->determineCashFlowPattern($operatingCashFlow, $investingCashFlow, $financingCashFlow);

            // Calculate cash flow ratios
            $ratios = $this->calculateCashFlowRatios($cooperativeId, $year, $operatingCashFlow);

            // Get historical comparison
            $historicalComparison = $this->getHistoricalCashFlowComparison($cooperativeId, $year, 3);

            return [
                'cash_flows' => [
                    'operating' => $operatingCashFlow,
                    'investing' => $investingCashFlow,
                    'financing' => $financingCashFlow,
                    'net' => $netCashFlow
                ],
                'pattern' => $pattern,
                'ratios' => $ratios,
                'historical_comparison' => $historicalComparison,
                'analysis_summary' => $this->generateCashFlowAnalysisSummary($operatingCashFlow, $investingCashFlow, $financingCashFlow, $pattern)
            ];
        } catch (\Exception $e) {
            Log::error('Error analyzing cash flow patterns', [
                'cooperative_id' => $cooperativeId,
                'year' => $year,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate cash flow forecast.
     */
    public function generateCashFlowForecast(int $cooperativeId, int $forecastYear, array $assumptions = []): array
    {
        try {
            // Get historical cash flow data
            $historicalYears = range($forecastYear - 3, $forecastYear - 1);
            $historicalData = [];

            foreach ($historicalYears as $year) {
                $report = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', 'cash_flow')
                    ->where('reporting_year', $year)
                    ->where('status', 'approved')
                    ->with('cashFlowActivities')
                    ->first();

                if ($report) {
                    $activities = $report->cashFlowActivities;

                    $historicalData[] = [
                        'year' => $year,
                        'operating' => $activities->where('activity_category', 'operating')->sum('current_year_amount'),
                        'investing' => $activities->where('activity_category', 'investing')->sum('current_year_amount'),
                        'financing' => $activities->where('activity_category', 'financing')->sum('current_year_amount')
                    ];
                }
            }

            if (count($historicalData) < 2) {
                throw new \Exception('Insufficient historical data for forecasting.');
            }

            // Apply forecasting methods
            $forecast = [
                'forecast_year' => $forecastYear,
                'scenarios' => [
                    'conservative' => $this->calculateConservativeForecast($historicalData, $assumptions),
                    'realistic' => $this->calculateRealisticForecast($historicalData, $assumptions),
                    'optimistic' => $this->calculateOptimisticForecast($historicalData, $assumptions)
                ],
                'assumptions' => $assumptions,
                'confidence_level' => $this->calculateForecastConfidence($historicalData),
                'key_factors' => $this->identifyKeyForecastFactors($historicalData)
            ];

            return $forecast;
        } catch (\Exception $e) {
            Log::error('Error generating cash flow forecast', [
                'cooperative_id' => $cooperativeId,
                'forecast_year' => $forecastYear,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Calculate free cash flow.
     */
    public function calculateFreeCashFlow(int $cooperativeId, int $year): array
    {
        try {
            $cashFlowReport = FinancialReport::where('cooperative_id', $cooperativeId)
                ->where('report_type', 'cash_flow')
                ->where('reporting_year', $year)
                ->where('status', 'approved')
                ->with('cashFlowActivities')
                ->first();

            if (!$cashFlowReport) {
                throw new \Exception('Cash flow report not found.');
            }

            $activities = $cashFlowReport->cashFlowActivities;

            $operatingCashFlow = $activities->where('activity_category', 'operating')
                ->where('is_subtotal', false)
                ->sum('current_year_amount');

            // Calculate capital expenditures (negative investing activities)
            $capitalExpenditures = $activities->where('activity_category', 'investing')
                ->where('current_year_amount', '<', 0)
                ->sum('current_year_amount');

            $freeCashFlow = $operatingCashFlow + $capitalExpenditures; // capex is negative

            // Calculate free cash flow metrics
            $metrics = $this->calculateFreeCashFlowMetrics($cooperativeId, $year, $freeCashFlow);

            return [
                'operating_cash_flow' => $operatingCashFlow,
                'capital_expenditures' => abs($capitalExpenditures),
                'free_cash_flow' => $freeCashFlow,
                'metrics' => $metrics,
                'interpretation' => $this->interpretFreeCashFlow($freeCashFlow, $operatingCashFlow)
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating free cash flow', [
                'cooperative_id' => $cooperativeId,
                'year' => $year,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Prepare cash flow data for storage.
     */
    private function prepareCashFlowData(array $data): array
    {
        $activities = $data['activities'] ?? [];

        $operatingCashFlow = 0;
        $investingCashFlow = 0;
        $financingCashFlow = 0;

        foreach ($activities as $activity) {
            if (!($activity['is_subtotal'] ?? false)) {
                $amount = (float) ($activity['current_year_amount'] ?? 0);

                switch ($activity['activity_category']) {
                    case 'operating':
                        $operatingCashFlow += $amount;
                        break;
                    case 'investing':
                        $investingCashFlow += $amount;
                        break;
                    case 'financing':
                        $financingCashFlow += $amount;
                        break;
                }
            }
        }

        $netCashFlow = $operatingCashFlow + $investingCashFlow + $financingCashFlow;

        return [
            'beginning_cash_balance' => $data['beginning_cash_balance'] ?? 0,
            'ending_cash_balance' => $data['ending_cash_balance'] ?? 0,
            'operating_cash_flow' => $operatingCashFlow,
            'investing_cash_flow' => $investingCashFlow,
            'financing_cash_flow' => $financingCashFlow,
            'net_cash_flow' => $netCashFlow,
            'activity_count' => count($activities),
            'generated_at' => now()->toISOString(),
            'generated_by' => auth()->user()->name
        ];
    }

    /**
     * Calculate net income from income statement.
     */
    private function calculateNetIncome(FinancialReport $incomeStatement): float
    {
        $totalRevenue = $incomeStatement->incomeStatementAccounts
            ->where('account_category', 'revenue')
            ->sum('current_year_amount');

        $totalExpenses = $incomeStatement->incomeStatementAccounts
            ->where('account_category', 'expense')
            ->sum('current_year_amount');

        return $totalRevenue - $totalExpenses;
    }

    /**
     * Get balance sheet changes between years.
     */
    private function getBalanceSheetChanges(int $cooperativeId, int $year): array
    {
        $currentYear = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('report_type', 'balance_sheet')
            ->where('reporting_year', $year)
            ->where('status', 'approved')
            ->with('balanceSheetAccounts')
            ->first();

        $previousYear = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('report_type', 'balance_sheet')
            ->where('reporting_year', $year - 1)
            ->where('status', 'approved')
            ->with('balanceSheetAccounts')
            ->first();

        $changes = [];

        if ($currentYear && $previousYear) {
            foreach ($currentYear->balanceSheetAccounts as $currentAccount) {
                $previousAccount = $previousYear->balanceSheetAccounts
                    ->where('account_code', $currentAccount->account_code)
                    ->first();

                $previousAmount = $previousAccount ? $previousAccount->current_year_amount : 0;
                $change = $currentAccount->current_year_amount - $previousAmount;

                $changes[$currentAccount->account_code] = [
                    'account_name' => $currentAccount->account_name,
                    'account_category' => $currentAccount->account_category,
                    'current_amount' => $currentAccount->current_year_amount,
                    'previous_amount' => $previousAmount,
                    'change' => $change
                ];
            }
        }

        return $changes;
    }

    /**
     * Calculate operating adjustments for indirect method.
     */
    private function calculateOperatingAdjustments(array $balanceSheetChanges): array
    {
        $adjustments = [];

        // Depreciation and amortization (would be positive adjustments)
        // This would typically come from detailed transaction data
        $adjustments['depreciation'] = 0;
        $adjustments['amortization'] = 0;

        // Other non-cash items
        $adjustments['bad_debt_provision'] = 0;

        return $adjustments;
    }

    /**
     * Calculate working capital changes.
     */
    private function calculateWorkingCapitalChanges(array $balanceSheetChanges): array
    {
        $workingCapitalChanges = [
            'accounts_receivable_change' => 0,
            'inventory_change' => 0,
            'accounts_payable_change' => 0,
            'other_current_assets_change' => 0,
            'other_current_liabilities_change' => 0
        ];

        foreach ($balanceSheetChanges as $change) {
            // Map balance sheet changes to working capital components
            // This would need to be customized based on account naming conventions
            if (strpos(strtolower($change['account_name']), 'piutang') !== false) {
                $workingCapitalChanges['accounts_receivable_change'] += $change['change'];
            } elseif (strpos(strtolower($change['account_name']), 'persediaan') !== false) {
                $workingCapitalChanges['inventory_change'] += $change['change'];
            } elseif (strpos(strtolower($change['account_name']), 'hutang') !== false) {
                $workingCapitalChanges['accounts_payable_change'] += $change['change'];
            }
        }

        // Calculate net working capital change (negative means cash inflow)
        $netChange = - ($workingCapitalChanges['accounts_receivable_change'] +
            $workingCapitalChanges['inventory_change'] -
            $workingCapitalChanges['accounts_payable_change']);

        $workingCapitalChanges['net_change'] = $netChange;

        return $workingCapitalChanges;
    }

    /**
     * Determine cash flow pattern.
     */
    private function determineCashFlowPattern(float $operating, float $investing, float $financing): array
    {
        $pattern = [
            'type' => 'unknown',
            'description' => '',
            'stage' => 'unknown'
        ];

        // Determine business lifecycle stage based on cash flow pattern
        if ($operating > 0 && $investing < 0 && $financing < 0) {
            $pattern['type'] = 'mature';
            $pattern['description'] = 'Positive operating cash flow, investing in growth, returning cash to stakeholders';
            $pattern['stage'] = 'mature';
        } elseif ($operating > 0 && $investing < 0 && $financing > 0) {
            $pattern['type'] = 'growth';
            $pattern['description'] = 'Positive operating cash flow, investing in expansion, raising additional capital';
            $pattern['stage'] = 'growth';
        } elseif ($operating < 0 && $investing < 0 && $financing > 0) {
            $pattern['type'] = 'startup';
            $pattern['description'] = 'Negative operating cash flow, investing in assets, raising capital';
            $pattern['stage'] = 'startup';
        } elseif ($operating < 0 && $investing > 0 && $financing < 0) {
            $pattern['type'] = 'distressed';
            $pattern['description'] = 'Negative operating cash flow, selling assets, repaying debt';
            $pattern['stage'] = 'decline';
        } else {
            $pattern['type'] = 'mixed';
            $pattern['description'] = 'Mixed cash flow pattern requiring further analysis';
            $pattern['stage'] = 'transitional';
        }

        return $pattern;
    }

    /**
     * Calculate cash flow ratios.
     */
    private function calculateCashFlowRatios(int $cooperativeId, int $year, float $operatingCashFlow): array
    {
        $ratios = [];

        // Get additional data for ratio calculations
        $balanceSheet = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('report_type', 'balance_sheet')
            ->where('reporting_year', $year)
            ->where('status', 'approved')
            ->with('balanceSheetAccounts')
            ->first();

        if ($balanceSheet) {
            $totalDebt = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'liability')
                ->sum('current_year_amount');

            $currentAssets = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'asset')
                ->where('account_name', 'like', '%lancar%')
                ->sum('current_year_amount');

            $ratios['operating_cash_flow_ratio'] = $currentAssets > 0 ? $operatingCashFlow / $currentAssets : 0;
            $ratios['cash_debt_coverage'] = $totalDebt > 0 ? $operatingCashFlow / $totalDebt : 0;
        }

        return $ratios;
    }

    /**
     * Get historical cash flow comparison.
     */
    private function getHistoricalCashFlowComparison(int $cooperativeId, int $currentYear, int $yearsBack): array
    {
        $years = range($currentYear - $yearsBack, $currentYear);
        $comparison = [];

        foreach ($years as $year) {
            $report = FinancialReport::where('cooperative_id', $cooperativeId)
                ->where('report_type', 'cash_flow')
                ->where('reporting_year', $year)
                ->where('status', 'approved')
                ->with('cashFlowActivities')
                ->first();

            if ($report) {
                $activities = $report->cashFlowActivities;

                $comparison[] = [
                    'year' => $year,
                    'operating' => $activities->where('activity_category', 'operating')->sum('current_year_amount'),
                    'investing' => $activities->where('activity_category', 'investing')->sum('current_year_amount'),
                    'financing' => $activities->where('activity_category', 'financing')->sum('current_year_amount'),
                    'net' => $activities->sum('current_year_amount')
                ];
            }
        }

        return $comparison;
    }

    /**
     * Generate cash flow analysis summary.
     */
    private function generateCashFlowAnalysisSummary(float $operating, float $investing, float $financing, array $pattern): array
    {
        $summary = [
            'overall_health' => 'moderate',
            'key_strengths' => [],
            'areas_of_concern' => [],
            'recommendations' => []
        ];

        // Assess operating cash flow
        if ($operating > 0) {
            $summary['key_strengths'][] = 'Positive operating cash flow indicates good operational performance';
            if ($operating > 1000000) {
                $summary['overall_health'] = 'strong';
            }
        } else {
            $summary['areas_of_concern'][] = 'Negative operating cash flow needs attention';
            $summary['overall_health'] = 'weak';
            $summary['recommendations'][] = 'Focus on improving operational efficiency and cash collection';
        }

        // Assess investing activities
        if ($investing < 0) {
            $summary['key_strengths'][] = 'Investment in growth and expansion';
        } elseif ($investing > 0 && $operating < 0) {
            $summary['areas_of_concern'][] = 'Selling assets while operations are struggling';
        }

        // Assess financing activities
        if ($financing > 0 && $operating < 0) {
            $summary['areas_of_concern'][] = 'Relying on external financing due to operational challenges';
        }

        // Pattern-based recommendations
        switch ($pattern['stage']) {
            case 'startup':
                $summary['recommendations'][] = 'Focus on achieving positive operating cash flow';
                break;
            case 'growth':
                $summary['recommendations'][] = 'Monitor debt levels while investing in growth';
                break;
            case 'mature':
                $summary['recommendations'][] = 'Consider optimizing capital allocation';
                break;
            case 'decline':
                $summary['recommendations'][] = 'Urgent need for operational restructuring';
                break;
        }

        return $summary;
    }

    /**
     * Calculate conservative forecast.
     */
    private function calculateConservativeForecast(array $historicalData, array $assumptions): array
    {
        $conservativeGrowthRate = $assumptions['conservative_growth_rate'] ?? -0.05; // -5% growth

        $lastYear = end($historicalData);

        return [
            'operating' => $lastYear['operating'] * (1 + $conservativeGrowthRate),
            'investing' => $lastYear['investing'] * (1 + $conservativeGrowthRate * 0.5),
            'financing' => $lastYear['financing'] * (1 + $conservativeGrowthRate * 0.3),
            'assumptions' => [
                'growth_rate' => $conservativeGrowthRate,
                'scenario' => 'Economic downturn, reduced operations'
            ]
        ];
    }

    /**
     * Calculate realistic forecast.
     */
    private function calculateRealisticForecast(array $historicalData, array $assumptions): array
    {
        // Calculate average growth rate from historical data
        $growthRates = [];
        for ($i = 1; $i < count($historicalData); $i++) {
            $current = $historicalData[$i];
            $previous = $historicalData[$i - 1];

            if ($previous['operating'] != 0) {
                $growthRates[] = ($current['operating'] - $previous['operating']) / abs($previous['operating']);
            }
        }

        $avgGrowthRate = count($growthRates) > 0 ? array_sum($growthRates) / count($growthRates) : 0;
        $realisticGrowthRate = $assumptions['realistic_growth_rate'] ?? $avgGrowthRate;

        $lastYear = end($historicalData);

        return [
            'operating' => $lastYear['operating'] * (1 + $realisticGrowthRate),
            'investing' => $lastYear['investing'] * (1 + $realisticGrowthRate * 0.8),
            'financing' => $lastYear['financing'] * (1 + $realisticGrowthRate * 0.5),
            'assumptions' => [
                'growth_rate' => $realisticGrowthRate,
                'scenario' => 'Normal business conditions continue'
            ]
        ];
    }

    /**
     * Calculate optimistic forecast.
     */
    private function calculateOptimisticForecast(array $historicalData, array $assumptions): array
    {
        $optimisticGrowthRate = $assumptions['optimistic_growth_rate'] ?? 0.15; // 15% growth

        $lastYear = end($historicalData);

        return [
            'operating' => $lastYear['operating'] * (1 + $optimisticGrowthRate),
            'investing' => $lastYear['investing'] * (1 + $optimisticGrowthRate * 1.2),
            'financing' => $lastYear['financing'] * (1 + $optimisticGrowthRate * 0.8),
            'assumptions' => [
                'growth_rate' => $optimisticGrowthRate,
                'scenario' => 'Favorable market conditions, expansion opportunities'
            ]
        ];
    }

    /**
     * Calculate forecast confidence level.
     */
    private function calculateForecastConfidence(array $historicalData): string
    {
        if (count($historicalData) < 2) {
            return 'low';
        }

        // Calculate variance in operating cash flows
        $operatingCashFlows = array_column($historicalData, 'operating');
        $variance = $this->calculateVariance($operatingCashFlows);
        $mean = array_sum($operatingCashFlows) / count($operatingCashFlows);

        $coefficientOfVariation = $mean != 0 ? abs($variance / $mean) : 1;

        if ($coefficientOfVariation < 0.2) {
            return 'high';
        } elseif ($coefficientOfVariation < 0.5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Identify key forecast factors.
     */
    private function identifyKeyForecastFactors(array $historicalData): array
    {
        return [
            'historical_volatility' => $this->calculateVolatility($historicalData),
            'trend_consistency' => $this->assessTrendConsistency($historicalData),
            'seasonal_patterns' => $this->identifySeasonalPatterns($historicalData),
            'external_factors' => [
                'Economic conditions',
                'Regulatory changes',
                'Market competition',
                'Interest rate environment'
            ]
        ];
    }

    /**
     * Calculate free cash flow metrics.
     */
    private function calculateFreeCashFlowMetrics(int $cooperativeId, int $year, float $freeCashFlow): array
    {
        $metrics = [];

        // Get additional data for metrics
        $balanceSheet = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('report_type', 'balance_sheet')
            ->where('reporting_year', $year)
            ->where('status', 'approved')
            ->with('balanceSheetAccounts')
            ->first();

        if ($balanceSheet) {
            $totalAssets = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'asset')
                ->sum('current_year_amount');

            $totalEquity = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'equity')
                ->sum('current_year_amount');

            $metrics['free_cash_flow_to_assets'] = $totalAssets > 0 ? ($freeCashFlow / $totalAssets) * 100 : 0;
            $metrics['free_cash_flow_to_equity'] = $totalEquity > 0 ? ($freeCashFlow / $totalEquity) * 100 : 0;
        }

        return $metrics;
    }

    /**
     * Interpret free cash flow.
     */
    private function interpretFreeCashFlow(float $freeCashFlow, float $operatingCashFlow): array
    {
        $interpretation = [
            'status' => 'neutral',
            'meaning' => '',
            'implications' => []
        ];

        if ($freeCashFlow > 0) {
            $interpretation['status'] = 'positive';
            $interpretation['meaning'] = 'The cooperative generates cash after necessary investments';
            $interpretation['implications'] = [
                'Can fund growth without external financing',
                'Can return cash to members',
                'Has financial flexibility'
            ];
        } elseif ($freeCashFlow < 0 && $operatingCashFlow > 0) {
            $interpretation['status'] = 'investing';
            $interpretation['meaning'] = 'The cooperative is investing heavily in growth';
            $interpretation['implications'] = [
                'High capital expenditure phase',
                'May need external financing',
                'Future cash flows should improve'
            ];
        } else {
            $interpretation['status'] = 'concerning';
            $interpretation['meaning'] = 'The cooperative has negative free cash flow and operational challenges';
            $interpretation['implications'] = [
                'May face liquidity issues',
                'Needs to improve operations',
                'Should reduce capital expenditures'
            ];
        }

        return $interpretation;
    }

    /**
     * Calculate variance.
     */
    private function calculateVariance(array $values): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);

        return array_sum($squaredDifferences) / (count($values) - 1);
    }

    /**
     * Calculate volatility.
     */
    private function calculateVolatility(array $historicalData): string
    {
        $operatingCashFlows = array_column($historicalData, 'operating');
        $variance = $this->calculateVariance($operatingCashFlows);
        $mean = array_sum($operatingCashFlows) / count($operatingCashFlows);

        $coefficientOfVariation = $mean != 0 ? sqrt($variance) / abs($mean) : 1;

        if ($coefficientOfVariation < 0.1) {
            return 'low';
        } elseif ($coefficientOfVariation < 0.3) {
            return 'medium';
        } else {
            return 'high';
        }
    }

    /**
     * Assess trend consistency.
     */
    private function assessTrendConsistency(array $historicalData): string
    {
        if (count($historicalData) < 3) {
            return 'insufficient_data';
        }

        $operatingCashFlows = array_column($historicalData, 'operating');
        $trends = [];

        for ($i = 1; $i < count($operatingCashFlows); $i++) {
            $trends[] = $operatingCashFlows[$i] > $operatingCashFlows[$i - 1] ? 'up' : 'down';
        }

        $upTrends = array_count_values($trends)['up'] ?? 0;
        $consistency = $upTrends / count($trends);

        if ($consistency >= 0.8 || $consistency <= 0.2) {
            return 'high';
        } elseif ($consistency >= 0.6 || $consistency <= 0.4) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Identify seasonal patterns.
     */
    private function identifySeasonalPatterns(array $historicalData): array
    {
        // This would require quarterly data to identify true seasonal patterns
        // For now, return a placeholder
        return [
            'pattern_detected' => false,
            'note' => 'Seasonal analysis requires quarterly data'
        ];
    }
}
