<?php
// app/Domain/Analytics/Providers/FinancialOverviewProvider.php
namespace App\Domain\Analytics\Providers;

use App\Domain\Analytics\Contracts\AnalyticsProviderInterface;
use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Financial Overview Analytics Provider
 * SRS Reference: Section 3.6.4 - Financial Analytics
 */
class FinancialOverviewProvider implements AnalyticsProviderInterface
{
    public function generate(AnalyticsRequestDTO $request): array
    {
        $dateRange = $request->getDateRange();

        return [
            'total_assets' => $this->calculateTotalAssets($request->cooperativeId, $dateRange),
            'total_liabilities' => $this->calculateTotalLiabilities($request->cooperativeId, $dateRange),
            'total_equity' => $this->calculateTotalEquity($request->cooperativeId, $dateRange),
            'net_income' => $this->calculateNetIncome($request->cooperativeId, $dateRange),
            'revenue_trends' => $this->getRevenueTrends($request->cooperativeId, $dateRange),
            'expense_breakdown' => $this->getExpenseBreakdown($request->cooperativeId, $dateRange),
            'financial_ratios' => $this->calculateFinancialRatios($request->cooperativeId, $dateRange),
            'cash_flow' => $this->getCashFlowData($request->cooperativeId, $dateRange),
        ];
    }

    public function getName(): string
    {
        return 'Financial Overview';
    }

    public function getDescription(): string
    {
        return 'Comprehensive financial overview including assets, liabilities, income, and key ratios';
    }

    public function getRequiredPermissions(): array
    {
        return ['view_financial_reports', 'view_accounts'];
    }

    public function getCacheKey(AnalyticsRequestDTO $request): string
    {
        return "financial_overview:{$request->cooperativeId}:{$request->period}:" . md5(serialize($request->filters));
    }

    public function getCacheTTL(): int
    {
        return 1800; // 30 minutes
    }

    public function validate(AnalyticsRequestDTO $request): bool
    {
        return $request->cooperativeId > 0;
    }

    public function getSupportedMetrics(): array
    {
        return [
            'total_assets',
            'total_liabilities',
            'total_equity',
            'net_income',
            'roa',
            'roe',
            'current_ratio',
            'debt_ratio'
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
            'cache_ttl' => 1800,
            'real_time' => false,
            'supported_periods' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']
        ];
    }

    /**
     * Calculate total assets
     */
    private function calculateTotalAssets(int $cooperativeId, array $dateRange): float
    {
        return Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'asset')
            ->where('is_active', true)
            ->sum('balance') ?? 0;
    }

    /**
     * Calculate total liabilities
     */
    private function calculateTotalLiabilities(int $cooperativeId, array $dateRange): float
    {
        return Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'liability')
            ->where('is_active', true)
            ->sum('balance') ?? 0;
    }

    /**
     * Calculate total equity
     */
    private function calculateTotalEquity(int $cooperativeId, array $dateRange): float
    {
        return Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'equity')
            ->where('is_active', true)
            ->sum('balance') ?? 0;
    }

    /**
     * Calculate net income
     */
    private function calculateNetIncome(int $cooperativeId, array $dateRange): float
    {
        $revenue = Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'revenue')
            ->where('is_active', true)
            ->sum('balance') ?? 0;

        $expenses = Account::where('cooperative_id', $cooperativeId)
            ->where('account_type', 'expense')
            ->where('is_active', true)
            ->sum('balance') ?? 0;

        return $revenue - $expenses;
    }

    /**
     * Get revenue trends
     */
    private function getRevenueTrends(int $cooperativeId, array $dateRange): array
    {
        return JournalEntry::where('cooperative_id', $cooperativeId)
            ->whereBetween('entry_date', [$dateRange['from'], $dateRange['to']])
            ->whereHas('lines', function ($query) {
                $query->whereHas('account', function ($q) {
                    $q->where('account_type', 'revenue');
                });
            })
            ->selectRaw('DATE(entry_date) as date, SUM(total_amount) as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
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
            ->select('account_name', 'balance')
            ->get()
            ->toArray();
    }

    /**
     * Calculate financial ratios
     */
    private function calculateFinancialRatios(int $cooperativeId, array $dateRange): array
    {
        $totalAssets = $this->calculateTotalAssets($cooperativeId, $dateRange);
        $totalLiabilities = $this->calculateTotalLiabilities($cooperativeId, $dateRange);
        $totalEquity = $this->calculateTotalEquity($cooperativeId, $dateRange);
        $netIncome = $this->calculateNetIncome($cooperativeId, $dateRange);

        return [
            'roa' => $totalAssets > 0 ? ($netIncome / $totalAssets) * 100 : 0,
            'roe' => $totalEquity > 0 ? ($netIncome / $totalEquity) * 100 : 0,
            'debt_ratio' => $totalAssets > 0 ? ($totalLiabilities / $totalAssets) * 100 : 0,
            'equity_ratio' => $totalAssets > 0 ? ($totalEquity / $totalAssets) * 100 : 0,
        ];
    }

    /**
     * Get cash flow data
     */
    private function getCashFlowData(int $cooperativeId, array $dateRange): array
    {
        // Implementation for cash flow calculation
        return [
            'operating_cash_flow' => 0,
            'investing_cash_flow' => 0,
            'financing_cash_flow' => 0,
            'net_cash_flow' => 0,
        ];
    }
}
