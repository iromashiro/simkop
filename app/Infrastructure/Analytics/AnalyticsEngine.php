<?php
// app/Infrastructure/Analytics/AnalyticsEngine.php
namespace App\Infrastructure\Analytics;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsEngine
{
    /**
     * Calculate KPI trends
     */
    public function calculateKPITrends(int $cooperativeId, string $kpi, int $months = 12): array
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths($months);

        return match ($kpi) {
            'total_assets' => $this->calculateAssetTrends($cooperativeId, $startDate, $endDate),
            'member_growth' => $this->calculateMemberGrowth($cooperativeId, $startDate, $endDate),
            'savings_growth' => $this->calculateSavingsGrowth($cooperativeId, $startDate, $endDate),
            'loan_portfolio' => $this->calculateLoanPortfolioTrends($cooperativeId, $startDate, $endDate),
            default => [],
        };
    }

    /**
     * Calculate asset trends
     */
    private function calculateAssetTrends(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        $trends = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $monthEnd = $current->copy()->endOfMonth();

            $assets = DB::table('accounts')
                ->join('journal_entry_lines', 'accounts.id', '=', 'journal_entry_lines.account_id')
                ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->where('accounts.cooperative_id', $cooperativeId)
                ->where('accounts.type', 'asset')
                ->where('journal_entries.entry_date', '<=', $monthEnd)
                ->where('journal_entries.status', 'approved')
                ->sum(DB::raw('journal_entry_lines.debit_amount - journal_entry_lines.credit_amount'));

            $trends[] = [
                'period' => $current->format('Y-m'),
                'value' => $assets,
                'date' => $current->toDateString(),
            ];

            $current->addMonth();
        }

        return $trends;
    }

    /**
     * Calculate member growth
     */
    private function calculateMemberGrowth(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        $trends = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $monthEnd = $current->copy()->endOfMonth();

            $memberCount = DB::table('members')
                ->where('cooperative_id', $cooperativeId)
                ->where('join_date', '<=', $monthEnd)
                ->count();

            $trends[] = [
                'period' => $current->format('Y-m'),
                'value' => $memberCount,
                'date' => $current->toDateString(),
            ];

            $current->addMonth();
        }

        return $trends;
    }

    /**
     * Calculate savings growth
     */
    private function calculateSavingsGrowth(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        $trends = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $monthEnd = $current->copy()->endOfMonth();

            $totalSavings = DB::table('savings_accounts')
                ->join('savings_transactions', 'savings_accounts.id', '=', 'savings_transactions.savings_account_id')
                ->where('savings_accounts.cooperative_id', $cooperativeId)
                ->where('savings_transactions.transaction_date', '<=', $monthEnd)
                ->sum(DB::raw("
                    CASE
                        WHEN savings_transactions.type = 'deposit' THEN savings_transactions.amount
                        WHEN savings_transactions.type = 'withdrawal' THEN -savings_transactions.amount
                        ELSE 0
                    END
                "));

            $trends[] = [
                'period' => $current->format('Y-m'),
                'value' => $totalSavings,
                'date' => $current->toDateString(),
            ];

            $current->addMonth();
        }

        return $trends;
    }

    /**
     * Calculate loan portfolio trends
     */
    private function calculateLoanPortfolioTrends(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        $trends = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $monthEnd = $current->copy()->endOfMonth();

            $outstandingLoans = DB::table('loan_accounts')
                ->where('cooperative_id', $cooperativeId)
                ->where('disbursement_date', '<=', $monthEnd)
                ->where(function ($query) use ($monthEnd) {
                    $query->whereNull('maturity_date')
                        ->orWhere('maturity_date', '>', $monthEnd);
                })
                ->sum('outstanding_balance');

            $trends[] = [
                'period' => $current->format('Y-m'),
                'value' => $outstandingLoans,
                'date' => $current->toDateString(),
            ];

            $current->addMonth();
        }

        return $trends;
    }

    /**
     * Generate financial ratios
     */
    public function calculateFinancialRatios(int $cooperativeId, Carbon $asOfDate): array
    {
        // Get balance sheet data
        $assets = $this->getTotalAssets($cooperativeId, $asOfDate);
        $liabilities = $this->getTotalLiabilities($cooperativeId, $asOfDate);
        $equity = $this->getTotalEquity($cooperativeId, $asOfDate);

        // Get income statement data
        $revenue = $this->getTotalRevenue($cooperativeId, $asOfDate->copy()->startOfYear(), $asOfDate);
        $expenses = $this->getTotalExpenses($cooperativeId, $asOfDate->copy()->startOfYear(), $asOfDate);
        $netIncome = $revenue - $expenses;

        return [
            'liquidity_ratios' => [
                'current_ratio' => $liabilities > 0 ? round($assets / $liabilities, 2) : 0,
                'debt_to_equity' => $equity > 0 ? round($liabilities / $equity, 2) : 0,
            ],
            'profitability_ratios' => [
                'return_on_assets' => $assets > 0 ? round(($netIncome / $assets) * 100, 2) : 0,
                'return_on_equity' => $equity > 0 ? round(($netIncome / $equity) * 100, 2) : 0,
                'profit_margin' => $revenue > 0 ? round(($netIncome / $revenue) * 100, 2) : 0,
            ],
            'efficiency_ratios' => [
                'asset_turnover' => $assets > 0 ? round($revenue / $assets, 2) : 0,
                'expense_ratio' => $revenue > 0 ? round(($expenses / $revenue) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Get total assets
     */
    private function getTotalAssets(int $cooperativeId, Carbon $asOfDate): float
    {
        return DB::table('accounts')
            ->join('journal_entry_lines', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'asset')
            ->where('journal_entries.entry_date', '<=', $asOfDate)
            ->where('journal_entries.status', 'approved')
            ->sum(DB::raw('journal_entry_lines.debit_amount - journal_entry_lines.credit_amount'));
    }

    /**
     * Get total liabilities
     */
    private function getTotalLiabilities(int $cooperativeId, Carbon $asOfDate): float
    {
        return DB::table('accounts')
            ->join('journal_entry_lines', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'liability')
            ->where('journal_entries.entry_date', '<=', $asOfDate)
            ->where('journal_entries.status', 'approved')
            ->sum(DB::raw('journal_entry_lines.credit_amount - journal_entry_lines.debit_amount'));
    }

    /**
     * Get total equity
     */
    private function getTotalEquity(int $cooperativeId, Carbon $asOfDate): float
    {
        return DB::table('accounts')
            ->join('journal_entry_lines', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'equity')
            ->where('journal_entries.entry_date', '<=', $asOfDate)
            ->where('journal_entries.status', 'approved')
            ->sum(DB::raw('journal_entry_lines.credit_amount - journal_entry_lines.debit_amount'));
    }

    /**
     * Get total revenue
     */
    private function getTotalRevenue(int $cooperativeId, Carbon $startDate, Carbon $endDate): float
    {
        return DB::table('accounts')
            ->join('journal_entry_lines', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'revenue')
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
            ->where('journal_entries.status', 'approved')
            ->sum(DB::raw('journal_entry_lines.credit_amount - journal_entry_lines.debit_amount'));
    }

    /**
     * Get total expenses
     */
    private function getTotalExpenses(int $cooperativeId, Carbon $startDate, Carbon $endDate): float
    {
        return DB::table('accounts')
            ->join('journal_entry_lines', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('accounts.cooperative_id', $cooperativeId)
            ->where('accounts.type', 'expense')
            ->whereBetween('journal_entries.entry_date', [$startDate, $endDate])
            ->where('journal_entries.status', 'approved')
            ->sum(DB::raw('journal_entry_lines.debit_amount - journal_entry_lines.credit_amount'));
    }
}
