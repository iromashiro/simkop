<?php
// app/Domain/Analytics/Providers/FinancialOverviewProvider.php
namespace App\Domain\Analytics\Providers;

use App\Domain\Analytics\Contracts\AnalyticsProviderInterface;
use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;
use App\Domain\Analytics\DTOs\WidgetDataDTO;
use Illuminate\Support\Facades\DB;

/**
 * Financial Overview Analytics Provider
 */
class FinancialOverviewProvider implements AnalyticsProviderInterface
{
    public function generate(AnalyticsRequestDTO $request): WidgetDataDTO
    {
        $dateRange = $this->getDateRange($request->period);

        // Get current period data
        $currentData = $this->getFinancialData($request->cooperativeId, $dateRange['current']);

        // Get previous period for comparison
        $previousData = $this->getFinancialData($request->cooperativeId, $dateRange['previous']);

        // Calculate growth rates
        $growthRates = $this->calculateGrowthRates($currentData, $previousData);

        return new WidgetDataDTO(
            type: 'financial_overview',
            title: 'Financial Overview',
            data: [
                'current_period' => $currentData,
                'previous_period' => $previousData,
                'growth_rates' => $growthRates,
                'key_metrics' => $this->calculateKeyMetrics($currentData),
            ],
            chartData: $this->generateChartData($currentData, $previousData),
            summary: $this->generateSummary($currentData, $growthRates)
        );
    }

    private function getFinancialData(int $cooperativeId, array $dateRange): array
    {
        // Get balance sheet data
        $balanceSheet = DB::table('accounts as a')
            ->leftJoin('journal_lines as jl', 'a.id', '=', 'jl.account_id')
            ->leftJoin('journal_entries as je', function ($join) use ($dateRange) {
                $join->on('jl.journal_entry_id', '=', 'je.id')
                    ->where('je.is_approved', true)
                    ->whereBetween('je.transaction_date', [$dateRange['start'], $dateRange['end']]);
            })
            ->where('a.cooperative_id', $cooperativeId)
            ->selectRaw('
                a.type,
                SUM(CASE
                    WHEN a.type IN (\'ASSET\', \'EXPENSE\')
                    THEN COALESCE(jl.debit_amount, 0) - COALESCE(jl.credit_amount, 0)
                    ELSE COALESCE(jl.credit_amount, 0) - COALESCE(jl.debit_amount, 0)
                END) as balance
            ')
            ->groupBy('a.type')
            ->get()
            ->keyBy('type');

        // Get income statement data
        $incomeStatement = DB::table('accounts as a')
            ->leftJoin('journal_lines as jl', 'a.id', '=', 'jl.account_id')
            ->leftJoin('journal_entries as je', function ($join) use ($dateRange) {
                $join->on('jl.journal_entry_id', '=', 'je.id')
                    ->where('je.is_approved', true)
                    ->whereBetween('je.transaction_date', [$dateRange['start'], $dateRange['end']]);
            })
            ->where('a.cooperative_id', $cooperativeId)
            ->whereIn('a.type', ['REVENUE', 'EXPENSE'])
            ->selectRaw('
                a.type,
                SUM(CASE
                    WHEN a.type = \'REVENUE\'
                    THEN COALESCE(jl.credit_amount, 0) - COALESCE(jl.debit_amount, 0)
                    ELSE COALESCE(jl.debit_amount, 0) - COALESCE(jl.credit_amount, 0)
                END) as amount
            ')
            ->groupBy('a.type')
            ->get()
            ->keyBy('type');

        return [
            'total_assets' => (float) ($balanceSheet['ASSET']->balance ?? 0),
            'total_liabilities' => (float) ($balanceSheet['LIABILITY']->balance ?? 0),
            'total_equity' => (float) ($balanceSheet['EQUITY']->balance ?? 0),
            'total_revenue' => (float) ($incomeStatement['REVENUE']->amount ?? 0),
            'total_expenses' => (float) ($incomeStatement['EXPENSE']->amount ?? 0),
            'net_income' => (float) (($incomeStatement['REVENUE']->amount ?? 0) - ($incomeStatement['EXPENSE']->amount ?? 0)),
        ];
    }

    private function calculateGrowthRates(array $current, array $previous): array
    {
        $growthRates = [];

        foreach ($current as $key => $value) {
            $previousValue = $previous[$key] ?? 0;

            if ($previousValue != 0) {
                $growthRates[$key] = (($value - $previousValue) / abs($previousValue)) * 100;
            } else {
                $growthRates[$key] = $value > 0 ? 100 : 0;
            }
        }

        return $growthRates;
    }

    private function calculateKeyMetrics(array $data): array
    {
        $totalAssets = $data['total_assets'];
        $totalEquity = $data['total_equity'];
        $netIncome = $data['net_income'];
        $totalRevenue = $data['total_revenue'];

        return [
            'debt_to_equity_ratio' => $totalEquity != 0 ? $data['total_liabilities'] / $totalEquity : 0,
            'return_on_assets' => $totalAssets != 0 ? ($netIncome / $totalAssets) * 100 : 0,
            'return_on_equity' => $totalEquity != 0 ? ($netIncome / $totalEquity) * 100 : 0,
            'profit_margin' => $totalRevenue != 0 ? ($netIncome / $totalRevenue) * 100 : 0,
            'asset_turnover' => $totalAssets != 0 ? $totalRevenue / $totalAssets : 0,
        ];
    }

    private function generateChartData(array $current, array $previous): array
    {
        return [
            'balance_sheet_comparison' => [
                'categories' => ['Assets', 'Liabilities', 'Equity'],
                'current' => [
                    $current['total_assets'],
                    $current['total_liabilities'],
                    $current['total_equity']
                ],
                'previous' => [
                    $previous['total_assets'] ?? 0,
                    $previous['total_liabilities'] ?? 0,
                    $previous['total_equity'] ?? 0
                ]
            ],
            'income_statement_comparison' => [
                'categories' => ['Revenue', 'Expenses', 'Net Income'],
                'current' => [
                    $current['total_revenue'],
                    $current['total_expenses'],
                    $current['net_income']
                ],
                'previous' => [
                    $previous['total_revenue'] ?? 0,
                    $previous['total_expenses'] ?? 0,
                    $previous['net_income'] ?? 0
                ]
            ]
        ];
    }

    private function generateSummary(array $data, array $growthRates): array
    {
        return [
            'total_assets' => number_format($data['total_assets'], 2),
            'net_income' => number_format($data['net_income'], 2),
            'asset_growth' => number_format($growthRates['total_assets'], 1) . '%',
            'income_growth' => number_format($growthRates['net_income'], 1) . '%',
        ];
    }

    private function getDateRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'monthly' => [
                'current' => [
                    'start' => $now->startOfMonth()->toDateString(),
                    'end' => $now->endOfMonth()->toDateString()
                ],
                'previous' => [
                    'start' => $now->subMonth()->startOfMonth()->toDateString(),
                    'end' => $now->endOfMonth()->toDateString()
                ]
            ],
            'quarterly' => [
                'current' => [
                    'start' => $now->startOfQuarter()->toDateString(),
                    'end' => $now->endOfQuarter()->toDateString()
                ],
                'previous' => [
                    'start' => $now->subQuarter()->startOfQuarter()->toDateString(),
                    'end' => $now->endOfQuarter()->toDateString()
                ]
            ],
            'yearly' => [
                'current' => [
                    'start' => $now->startOfYear()->toDateString(),
                    'end' => $now->endOfYear()->toDateString()
                ],
                'previous' => [
                    'start' => $now->subYear()->startOfYear()->toDateString(),
                    'end' => $now->endOfYear()->toDateString()
                ]
            ],
            default => throw new \InvalidArgumentException("Invalid period: {$period}")
        };
    }
}
