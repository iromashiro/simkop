<?php
// app/Domain/Analytics/Providers/ProfitabilityProvider.php
namespace App\Domain\Analytics\Providers;

use App\Domain\Analytics\Contracts\AnalyticsProviderInterface;
use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;
use App\Domain\Analytics\DTOs\WidgetDataDTO;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Profitability Analytics Provider
 * SRS Reference: Section 3.6.7 - Profitability Analysis
 */
class ProfitabilityProvider implements AnalyticsProviderInterface
{
    public function generate(AnalyticsRequestDTO $request): WidgetDataDTO
    {
        $dateRange = $request->getDateRange();

        $profitabilityData = [
            'net_income' => $this->calculateNetIncome($request->cooperativeId, $dateRange),
            'gross_profit' => $this->calculateGrossProfit($request->cooperativeId, $dateRange),
            'operating_income' => $this->calculateOperatingIncome($request->cooperativeId, $dateRange),
            'revenue_breakdown' => $this->getRevenueBreakdown($request->cooperativeId, $dateRange),
            'expense_breakdown' => $this->getExpenseBreakdown($request->cooperativeId, $dateRange),
            'profitability_ratios' => $this->calculateProfitabilityRatios($request->cooperativeId, $dateRange),
            'monthly_trends' => $this->getMonthlyProfitabilityTrends($request->cooperativeId, $dateRange),
            'cost_analysis' => $this->getCostAnalysis($request->cooperativeId, $dateRange),
        ];

        return WidgetDataDTO::financial(
            title: 'Profitability Analysis',
            data: $profitabilityData,
            chartConfig: $this->getDefaultChartConfig(),
            description: 'Comprehensive profitability analysis and financial performance metrics'
        );
    }

    public function getName(): string
    {
        return 'Profitability Analysis';
    }

    public function getDescription(): string
    {
        return 'Profitability analysis including revenue, expenses, margins, and financial performance ratios';
    }

    public function getRequiredPermissions(): array
    {
        return ['view_financial_reports', 'view_profit_loss'];
    }

    public function getCacheKey(AnalyticsRequestDTO $request): string
    {
        return "profitability:{$request->cooperativeId}:{$request->period}:" . md5(serialize($request->filters));
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
            'net_income',
            'gross_profit',
            'operating_income',
            'profit_margin',
            'roa',
            'roe'
        ];
    }

    public function supportsRealTime(): bool
    {
        return false;
    }

    public function getConfiguration(): array
    {
        return [
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            'real_time' => false,
            'supported_periods' => ['monthly', 'quarterly', 'yearly']
        ];
    }

    public function getWidgetType(): string
    {
        return 'financial';
    }

    public function getDefaultChartConfig(): array
    {
        return [
            'type' => 'bar',
            'options' => [
                'responsive' => true,
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'ticks' => [
                            'callback' => 'function(value) { return "Rp " + value.toLocaleString(); }'
                        ]
                    ]
                ],
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'top'
                    ]
                ]
            ]
        ];
    }

    public function supportsPeriod(string $period): bool
    {
        return in_array($period, ['monthly', 'quarterly', 'yearly']);
    }

    /**
     * Calculate net income
     */
    private function calculateNetIncome(int $cooperativeId, array $dateRange): float
    {
        $revenue = $this->getTotalRevenue($cooperativeId, $dateRange);
        $expenses = $this->getTotalExpenses($cooperativeId, $dateRange);

        return $revenue - $expenses;
    }

    /**
     * Calculate gross profit
     */
    private function calculateGrossProfit(int $cooperativeId, array $dateRange): float
    {
        $revenue = $this->getTotalRevenue($cooperativeId, $dateRange);
        $cogs = $this->getCostOfGoodsSold($cooperativeId, $dateRange);

        return $revenue - $cogs;
    }

    /**
     * Calculate operating income
     */
    private function calculateOperatingIncome(int $cooperativeId, array $dateRange): float
    {
        $grossProfit = $this->calculateGrossProfit($cooperativeId, $dateRange);
        $operatingExpenses = $this->getOperatingExpenses($cooperativeId, $dateRange);

        return $grossProfit - $operatingExpenses;
    }

    /**
     * Get total revenue
     */
    private function getTotalRevenue(int $cooperativeId, array $dateRange): float
    {
        return Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'revenue')
            ->where('is_active', true)
            ->sum('balance') ?? 0;
    }

    /**
     * Get total expenses
     */
    private function getTotalExpenses(int $cooperativeId, array $dateRange): float
    {
        return Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'expense')
            ->where('is_active', true)
            ->sum('balance') ?? 0;
    }

    /**
     * Get cost of goods sold
     */
    private function getCostOfGoodsSold(int $cooperativeId, array $dateRange): float
    {
        return Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'expense')
            ->where('account_subtype', 'cogs')
            ->where('is_active', true)
            ->sum('balance') ?? 0;
    }

    /**
     * Get operating expenses
     */
    private function getOperatingExpenses(int $cooperativeId, array $dateRange): float
    {
        return Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'expense')
            ->where('account_subtype', 'operating')
            ->where('is_active', true)
            ->sum('balance') ?? 0;
    }

    /**
     * Get revenue breakdown
     */
    private function getRevenueBreakdown(int $cooperativeId, array $dateRange): array
    {
        return Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'revenue')
            ->where('is_active', true)
            ->select('account_name', 'balance', 'account_subtype')
            ->get()
            ->groupBy('account_subtype')
            ->map(function ($accounts) {
                return [
                    'total' => $accounts->sum('balance'),
                    'accounts' => $accounts->toArray()
                ];
            })
            ->toArray();
    }

    /**
     * Get expense breakdown
     */
    private function getExpenseBreakdown(int $cooperativeId, array $dateRange): array
    {
        return Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'expense')
            ->where('is_active', true)
            ->select('account_name', 'balance', 'account_subtype')
            ->get()
            ->groupBy('account_subtype')
            ->map(function ($accounts) {
                return [
                    'total' => $accounts->sum('balance'),
                    'accounts' => $accounts->toArray()
                ];
            })
            ->toArray();
    }

    /**
     * Calculate profitability ratios
     */
    private function calculateProfitabilityRatios(int $cooperativeId, array $dateRange): array
    {
        $revenue = $this->getTotalRevenue($cooperativeId, $dateRange);
        $netIncome = $this->calculateNetIncome($cooperativeId, $dateRange);
        $grossProfit = $this->calculateGrossProfit($cooperativeId, $dateRange);
        $operatingIncome = $this->calculateOperatingIncome($cooperativeId, $dateRange);

        // Get total assets for ROA calculation
        $totalAssets = Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'asset')
            ->where('is_active', true)
            ->sum('balance') ?? 0;

        // Get total equity for ROE calculation
        $totalEquity = Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'equity')
            ->where('is_active', true)
            ->sum('balance') ?? 0;

        return [
            'gross_profit_margin' => $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0,
            'operating_profit_margin' => $revenue > 0 ? ($operatingIncome / $revenue) * 100 : 0,
            'net_profit_margin' => $revenue > 0 ? ($netIncome / $revenue) * 100 : 0,
            'roa' => $totalAssets > 0 ? ($netIncome / $totalAssets) * 100 : 0,
            'roe' => $totalEquity > 0 ? ($netIncome / $totalEquity) * 100 : 0,
        ];
    }

    /**
     * Get monthly profitability trends
     */
    private function getMonthlyProfitabilityTrends(int $cooperativeId, array $dateRange): array
    {
        return JournalEntry::where('cooperative_id', $cooperativeId)
            ->whereBetween('entry_date', [$dateRange['from'], $dateRange['to']])
            ->selectRaw('
                YEAR(entry_date) as year,
                MONTH(entry_date) as month,
                SUM(CASE WHEN accounts.account_type = "revenue" THEN journal_entry_lines.credit_amount ELSE 0 END) as revenue,
                SUM(CASE WHEN accounts.account_type = "expense" THEN journal_entry_lines.debit_amount ELSE 0 END) as expenses
            ')
            ->join('journal_entry_lines', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                    'revenue' => $item->revenue,
                    'expenses' => $item->expenses,
                    'net_income' => $item->revenue - $item->expenses,
                ];
            })
            ->toArray();
    }

    /**
     * Get cost analysis
     */
    private function getCostAnalysis(int $cooperativeId, array $dateRange): array
    {
        $totalExpenses = $this->getTotalExpenses($cooperativeId, $dateRange);

        return [
            'fixed_costs' => $this->getFixedCosts($cooperativeId, $dateRange),
            'variable_costs' => $this->getVariableCosts($cooperativeId, $dateRange),
            'cost_per_member' => $this->getCostPerMember($cooperativeId, $dateRange),
            'expense_ratio' => $this->getExpenseRatio($cooperativeId, $dateRange),
        ];
    }

    /**
     * Get fixed costs
     */
    private function getFixedCosts(int $cooperativeId, array $dateRange): float
    {
        return Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'expense')
            ->where('cost_type', 'fixed')
            ->where('is_active', true)
            ->sum('balance') ?? 0;
    }

    /**
     * Get variable costs
     */
    private function getVariableCosts(int $cooperativeId, array $dateRange): float
    {
        return Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'expense')
            ->where('cost_type', 'variable')
            ->where('is_active', true)
            ->sum('balance') ?? 0;
    }

    /**
     * Get cost per member
     */
    private function getCostPerMember(int $cooperativeId, array $dateRange): float
    {
        $totalExpenses = $this->getTotalExpenses($cooperativeId, $dateRange);
        $memberCount = \App\Domain\Member\Models\Member::where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->count();

        return $memberCount > 0 ? $totalExpenses / $memberCount : 0;
    }

    /**
     * Get expense ratio
     */
    private function getExpenseRatio(int $cooperativeId, array $dateRange): float
    {
        $totalRevenue = $this->getTotalRevenue($cooperativeId, $dateRange);
        $totalExpenses = $this->getTotalExpenses($cooperativeId, $dateRange);

        return $totalRevenue > 0 ? ($totalExpenses / $totalRevenue) * 100 : 0;
    }
}
