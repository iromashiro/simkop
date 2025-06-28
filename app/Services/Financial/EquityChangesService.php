<?php

namespace App\Services\Financial;

use App\Models\Financial\FinancialReport;
use App\Models\Financial\EquityChange;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EquityChangesService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Calculate equity changes for a cooperative.
     */
    public function calculateEquityChanges(int $cooperativeId, int $year): array
    {
        try {
            // Get previous year equity balances
            $previousYearEquity = $this->getPreviousYearEquity($cooperativeId, $year - 1);

            // Get current year transactions affecting equity
            $currentYearTransactions = $this->getCurrentYearEquityTransactions($cooperativeId, $year);

            // Calculate changes for each equity component
            $equityChanges = [];

            foreach ($this->getEquityComponents() as $component) {
                $beginningBalance = $previousYearEquity[$component] ?? 0;
                $additions = $currentYearTransactions[$component]['additions'] ?? 0;
                $reductions = $currentYearTransactions[$component]['reductions'] ?? 0;
                $endingBalance = $beginningBalance + $additions - $reductions;

                $equityChanges[] = [
                    'equity_component' => $component,
                    'beginning_balance' => $beginningBalance,
                    'additions' => $additions,
                    'reductions' => $reductions,
                    'ending_balance' => $endingBalance,
                    'change_amount' => $endingBalance - $beginningBalance,
                    'change_percentage' => $beginningBalance > 0 ? (($endingBalance - $beginningBalance) / $beginningBalance) * 100 : 0
                ];
            }

            return [
                'equity_changes' => $equityChanges,
                'total_beginning_balance' => array_sum(array_column($equityChanges, 'beginning_balance')),
                'total_additions' => array_sum(array_column($equityChanges, 'additions')),
                'total_reductions' => array_sum(array_column($equityChanges, 'reductions')),
                'total_ending_balance' => array_sum(array_column($equityChanges, 'ending_balance')),
                'net_change' => array_sum(array_column($equityChanges, 'change_amount'))
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating equity changes', [
                'cooperative_id' => $cooperativeId,
                'year' => $year,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate equity changes report.
     */
    public function generateEquityChangesReport(array $data): FinancialReport
    {
        return DB::transaction(function () use ($data) {
            try {
                // Create the main report
                $report = FinancialReport::create([
                    'cooperative_id' => $data['cooperative_id'],
                    'report_type' => 'equity_changes',
                    'reporting_year' => $data['reporting_year'],
                    'reporting_period' => $data['reporting_period'],
                    'status' => $data['status'] ?? 'draft',
                    'notes' => $data['notes'] ?? null,
                    'data' => $this->prepareEquityChangesData($data),
                    'created_by' => auth()->id(),
                ]);

                // Create equity change records
                foreach ($data['equity_changes'] as $changeData) {
                    $report->equityChanges()->create([
                        'equity_component' => $changeData['equity_component'],
                        'beginning_balance' => $changeData['beginning_balance'],
                        'additions' => $changeData['additions'] ?? 0,
                        'reductions' => $changeData['reductions'] ?? 0,
                        'ending_balance' => $changeData['ending_balance'],
                        'note_reference' => $changeData['note_reference'] ?? null,
                        'sort_order' => $changeData['sort_order'] ?? 0,
                    ]);
                }

                // Log the generation
                $this->auditLogService->log(
                    'equity_changes_report_generated',
                    'Equity changes report generated',
                    [
                        'report_id' => $report->id,
                        'cooperative_id' => $report->cooperative_id,
                        'reporting_year' => $report->reporting_year
                    ]
                );

                return $report->load('equityChanges');
            } catch (\Exception $e) {
                Log::error('Error generating equity changes report', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                    'user_id' => auth()->id()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Analyze equity composition and trends.
     */
    public function analyzeEquityComposition(int $cooperativeId, int $year): array
    {
        try {
            $report = FinancialReport::where('cooperative_id', $cooperativeId)
                ->where('report_type', 'equity_changes')
                ->where('reporting_year', $year)
                ->where('status', 'approved')
                ->with('equityChanges')
                ->first();

            if (!$report) {
                throw new \Exception('Equity changes report not found for the specified year.');
            }

            $equityChanges = $report->equityChanges;
            $totalEquity = $equityChanges->sum('ending_balance');

            $composition = [];
            $trends = [];

            foreach ($equityChanges as $change) {
                // Calculate composition percentage
                $percentage = $totalEquity > 0 ? ($change->ending_balance / $totalEquity) * 100 : 0;

                $composition[] = [
                    'component' => $change->equity_component,
                    'amount' => $change->ending_balance,
                    'percentage' => $percentage,
                    'change_from_beginning' => $change->ending_balance - $change->beginning_balance,
                    'growth_rate' => $change->beginning_balance > 0 ?
                        (($change->ending_balance - $change->beginning_balance) / $change->beginning_balance) * 100 : 0
                ];

                // Get historical trend
                $historicalData = $this->getHistoricalEquityData($cooperativeId, $change->equity_component, $year, 5);
                $trends[$change->equity_component] = $historicalData;
            }

            // Calculate equity ratios
            $ratios = $this->calculateEquityRatios($cooperativeId, $year);

            return [
                'composition' => $composition,
                'trends' => $trends,
                'ratios' => $ratios,
                'total_equity' => $totalEquity,
                'analysis_summary' => $this->generateEquityAnalysisSummary($composition, $ratios)
            ];
        } catch (\Exception $e) {
            Log::error('Error analyzing equity composition', [
                'cooperative_id' => $cooperativeId,
                'year' => $year,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Compare equity changes across multiple years.
     */
    public function compareEquityAcrossYears(int $cooperativeId, array $years): array
    {
        try {
            $comparison = [];

            foreach ($years as $year) {
                $report = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', 'equity_changes')
                    ->where('reporting_year', $year)
                    ->where('status', 'approved')
                    ->with('equityChanges')
                    ->first();

                if ($report) {
                    $yearData = [
                        'year' => $year,
                        'total_equity' => $report->equityChanges->sum('ending_balance'),
                        'components' => []
                    ];

                    foreach ($report->equityChanges as $change) {
                        $yearData['components'][$change->equity_component] = [
                            'beginning_balance' => $change->beginning_balance,
                            'additions' => $change->additions,
                            'reductions' => $change->reductions,
                            'ending_balance' => $change->ending_balance,
                            'net_change' => $change->ending_balance - $change->beginning_balance
                        ];
                    }

                    $comparison[] = $yearData;
                }
            }

            // Calculate year-over-year changes
            $yoyChanges = $this->calculateYearOverYearChanges($comparison);

            return [
                'comparison' => $comparison,
                'year_over_year_changes' => $yoyChanges,
                'trends_analysis' => $this->analyzeTrends($comparison)
            ];
        } catch (\Exception $e) {
            Log::error('Error comparing equity across years', [
                'cooperative_id' => $cooperativeId,
                'years' => $years,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate equity forecast based on historical data.
     */
    public function generateEquityForecast(int $cooperativeId, int $forecastYear): array
    {
        try {
            // Get 5 years of historical data
            $historicalYears = range($forecastYear - 5, $forecastYear - 1);
            $historicalData = $this->compareEquityAcrossYears($cooperativeId, $historicalYears);

            $forecast = [];

            foreach ($this->getEquityComponents() as $component) {
                $componentData = [];

                foreach ($historicalData['comparison'] as $yearData) {
                    if (isset($yearData['components'][$component])) {
                        $componentData[] = [
                            'year' => $yearData['year'],
                            'ending_balance' => $yearData['components'][$component]['ending_balance']
                        ];
                    }
                }

                if (count($componentData) >= 3) {
                    // Simple linear regression forecast
                    $forecastAmount = $this->calculateLinearForecast($componentData, $forecastYear);

                    $forecast[$component] = [
                        'forecasted_amount' => max(0, $forecastAmount), // Ensure non-negative
                        'confidence_level' => $this->calculateConfidenceLevel($componentData),
                        'historical_average' => array_sum(array_column($componentData, 'ending_balance')) / count($componentData),
                        'trend' => $this->determineTrend($componentData)
                    ];
                }
            }

            return [
                'forecast_year' => $forecastYear,
                'component_forecasts' => $forecast,
                'total_forecasted_equity' => array_sum(array_column($forecast, 'forecasted_amount')),
                'forecast_assumptions' => $this->getForecastAssumptions(),
                'generated_at' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error('Error generating equity forecast', [
                'cooperative_id' => $cooperativeId,
                'forecast_year' => $forecastYear,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get previous year equity balances.
     */
    private function getPreviousYearEquity(int $cooperativeId, int $year): array
    {
        $previousReport = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('report_type', 'equity_changes')
            ->where('reporting_year', $year)
            ->where('status', 'approved')
            ->with('equityChanges')
            ->first();

        $equity = [];

        if ($previousReport) {
            foreach ($previousReport->equityChanges as $change) {
                $equity[$change->equity_component] = $change->ending_balance;
            }
        }

        return $equity;
    }

    /**
     * Get current year equity transactions.
     */
    private function getCurrentYearEquityTransactions(int $cooperativeId, int $year): array
    {
        // This would typically come from transaction records
        // For now, return empty array as placeholder
        return [];
    }

    /**
     * Get equity components.
     */
    private function getEquityComponents(): array
    {
        return [
            'simpanan_pokok',
            'simpanan_wajib',
            'simpanan_sukarela',
            'cadangan',
            'shu_belum_dibagi',
            'laba_ditahan'
        ];
    }

    /**
     * Prepare equity changes data for storage.
     */
    private function prepareEquityChangesData(array $data): array
    {
        $equityChanges = $data['equity_changes'] ?? [];

        return [
            'total_beginning_balance' => array_sum(array_column($equityChanges, 'beginning_balance')),
            'total_additions' => array_sum(array_column($equityChanges, 'additions')),
            'total_reductions' => array_sum(array_column($equityChanges, 'reductions')),
            'total_ending_balance' => array_sum(array_column($equityChanges, 'ending_balance')),
            'net_change' => array_sum(array_column($equityChanges, 'ending_balance')) -
                array_sum(array_column($equityChanges, 'beginning_balance')),
            'component_count' => count($equityChanges),
            'generated_at' => now()->toISOString(),
            'generated_by' => auth()->user()->name
        ];
    }

    /**
     * Get historical equity data for a component.
     */
    private function getHistoricalEquityData(int $cooperativeId, string $component, int $currentYear, int $yearsBack): array
    {
        $years = range($currentYear - $yearsBack, $currentYear);
        $historicalData = [];

        foreach ($years as $year) {
            $report = FinancialReport::where('cooperative_id', $cooperativeId)
                ->where('report_type', 'equity_changes')
                ->where('reporting_year', $year)
                ->where('status', 'approved')
                ->with('equityChanges')
                ->first();

            if ($report) {
                $equityChange = $report->equityChanges->where('equity_component', $component)->first();
                if ($equityChange) {
                    $historicalData[] = [
                        'year' => $year,
                        'beginning_balance' => $equityChange->beginning_balance,
                        'ending_balance' => $equityChange->ending_balance,
                        'net_change' => $equityChange->ending_balance - $equityChange->beginning_balance
                    ];
                }
            }
        }

        return $historicalData;
    }

    /**
     * Calculate equity ratios.
     */
    private function calculateEquityRatios(int $cooperativeId, int $year): array
    {
        // Get balance sheet data for ratio calculations
        $balanceSheet = FinancialReport::where('cooperative_id', $cooperativeId)
            ->where('report_type', 'balance_sheet')
            ->where('reporting_year', $year)
            ->where('status', 'approved')
            ->with('balanceSheetAccounts')
            ->first();

        $ratios = [];

        if ($balanceSheet) {
            $totalAssets = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'asset')
                ->sum('current_year_amount');

            $totalLiabilities = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'liability')
                ->sum('current_year_amount');

            $totalEquity = $balanceSheet->balanceSheetAccounts
                ->where('account_category', 'equity')
                ->sum('current_year_amount');

            $ratios = [
                'equity_ratio' => $totalAssets > 0 ? ($totalEquity / $totalAssets) * 100 : 0,
                'debt_to_equity_ratio' => $totalEquity > 0 ? $totalLiabilities / $totalEquity : 0,
                'equity_multiplier' => $totalEquity > 0 ? $totalAssets / $totalEquity : 0
            ];
        }

        return $ratios;
    }

    /**
     * Generate equity analysis summary.
     */
    private function generateEquityAnalysisSummary(array $composition, array $ratios): array
    {
        $summary = [
            'dominant_component' => null,
            'equity_strength' => 'moderate',
            'growth_components' => [],
            'declining_components' => [],
            'recommendations' => []
        ];

        // Find dominant component
        $maxPercentage = 0;
        foreach ($composition as $component) {
            if ($component['percentage'] > $maxPercentage) {
                $maxPercentage = $component['percentage'];
                $summary['dominant_component'] = $component['component'];
            }

            // Identify growing and declining components
            if ($component['growth_rate'] > 5) {
                $summary['growth_components'][] = $component['component'];
            } elseif ($component['growth_rate'] < -5) {
                $summary['declining_components'][] = $component['component'];
            }
        }

        // Assess equity strength
        $equityRatio = $ratios['equity_ratio'] ?? 0;
        if ($equityRatio > 60) {
            $summary['equity_strength'] = 'strong';
        } elseif ($equityRatio < 30) {
            $summary['equity_strength'] = 'weak';
        }

        // Generate recommendations
        if ($equityRatio < 30) {
            $summary['recommendations'][] = 'Pertimbangkan untuk meningkatkan modal sendiri';
        }

        if (count($summary['declining_components']) > 2) {
            $summary['recommendations'][] = 'Perhatikan komponen ekuitas yang menurun';
        }

        return $summary;
    }

    /**
     * Calculate year-over-year changes.
     */
    private function calculateYearOverYearChanges(array $comparison): array
    {
        $yoyChanges = [];

        for ($i = 1; $i < count($comparison); $i++) {
            $currentYear = $comparison[$i];
            $previousYear = $comparison[$i - 1];

            $totalChange = $currentYear['total_equity'] - $previousYear['total_equity'];
            $totalChangePercentage = $previousYear['total_equity'] > 0 ?
                ($totalChange / $previousYear['total_equity']) * 100 : 0;

            $yoyChanges[] = [
                'from_year' => $previousYear['year'],
                'to_year' => $currentYear['year'],
                'total_change' => $totalChange,
                'total_change_percentage' => $totalChangePercentage,
                'component_changes' => $this->calculateComponentChanges($currentYear, $previousYear)
            ];
        }

        return $yoyChanges;
    }

    /**
     * Calculate component changes between years.
     */
    private function calculateComponentChanges(array $currentYear, array $previousYear): array
    {
        $componentChanges = [];

        foreach ($this->getEquityComponents() as $component) {
            $currentAmount = $currentYear['components'][$component]['ending_balance'] ?? 0;
            $previousAmount = $previousYear['components'][$component]['ending_balance'] ?? 0;

            $change = $currentAmount - $previousAmount;
            $changePercentage = $previousAmount > 0 ? ($change / $previousAmount) * 100 : 0;

            $componentChanges[$component] = [
                'change' => $change,
                'change_percentage' => $changePercentage
            ];
        }

        return $componentChanges;
    }

    /**
     * Analyze trends in equity data.
     */
    private function analyzeTrends(array $comparison): array
    {
        $trends = [];

        foreach ($this->getEquityComponents() as $component) {
            $values = [];
            foreach ($comparison as $yearData) {
                if (isset($yearData['components'][$component])) {
                    $values[] = $yearData['components'][$component]['ending_balance'];
                }
            }

            if (count($values) >= 3) {
                $trends[$component] = $this->determineTrend($values);
            }
        }

        return $trends;
    }

    /**
     * Calculate linear forecast.
     */
    private function calculateLinearForecast(array $data, int $forecastYear): float
    {
        $n = count($data);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($data as $point) {
            $x = $point['year'];
            $y = $point['ending_balance'];

            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        return $slope * $forecastYear + $intercept;
    }

    /**
     * Calculate confidence level for forecast.
     */
    private function calculateConfidenceLevel(array $data): string
    {
        $variance = $this->calculateVariance(array_column($data, 'ending_balance'));
        $mean = array_sum(array_column($data, 'ending_balance')) / count($data);

        $coefficientOfVariation = $mean > 0 ? ($variance / $mean) * 100 : 100;

        if ($coefficientOfVariation < 10) {
            return 'high';
        } elseif ($coefficientOfVariation < 25) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate variance.
     */
    private function calculateVariance(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);

        return array_sum($squaredDifferences) / count($values);
    }

    /**
     * Determine trend direction.
     */
    private function determineTrend($data): string
    {
        if (is_array($data) && count($data) >= 2) {
            $values = is_array($data[0]) ? array_column($data, 'ending_balance') : $data;
            $first = reset($values);
            $last = end($values);

            $change = ($last - $first) / $first * 100;

            if ($change > 5) {
                return 'increasing';
            } elseif ($change < -5) {
                return 'decreasing';
            } else {
                return 'stable';
            }
        }

        return 'unknown';
    }

    /**
     * Get forecast assumptions.
     */
    private function getForecastAssumptions(): array
    {
        return [
            'method' => 'Linear regression based on historical data',
            'data_points' => 'Minimum 3 years of historical data required',
            'limitations' => [
                'Does not account for economic cycles',
                'Assumes historical trends continue',
                'External factors not considered'
            ],
            'confidence_factors' => [
                'Data consistency',
                'Historical variance',
                'Trend stability'
            ]
        ];
    }
}
