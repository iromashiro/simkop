<?php
// app/Domain/Report/Reports/BalanceSheetReport.php
namespace App\Domain\Report\Reports;

use App\Domain\Report\Abstracts\BaseReport;
use App\Domain\Report\DTOs\ReportParameterDTO;
use Illuminate\Support\Facades\DB;

/**
 * SECURITY HARDENED: Balance Sheet Report with SQL injection protection
 * SRS Reference: Section 3.4.1 - Balance Sheet Requirements
 */
class BalanceSheetReport extends BaseReport
{
    protected string $reportCode = 'BALANCE_SHEET';
    protected string $reportName = 'Neraca (Balance Sheet)';
    protected string $description = 'Laporan posisi keuangan koperasi pada tanggal tertentu';
    protected array $requiredParameters = [];
    protected int $cacheMinutes = 60;

    protected function generateReportData(ReportParameterDTO $parameters): array
    {
        $balanceDate = $parameters->endDate;

        // SECURITY FIX: Use query builder instead of raw SQL
        $accountBalances = $this->getAccountBalances($balanceDate, $parameters->cooperativeId);

        // Group by account categories
        $assets = $this->groupAccountsByCategory($accountBalances, ['ASSET']);
        $liabilities = $this->groupAccountsByCategory($accountBalances, ['LIABILITY']);
        $equity = $this->groupAccountsByCategory($accountBalances, ['EQUITY']);

        return [
            'balance_date' => $balanceDate->format('Y-m-d'),
            'assets' => $this->buildAccountHierarchy($assets),
            'liabilities' => $this->buildAccountHierarchy($liabilities),
            'equity' => $this->buildAccountHierarchy($equity),
        ];
    }

    protected function generateSummary(array $data, ReportParameterDTO $parameters): array
    {
        $totalAssets = $this->calculateCategoryTotal($data['assets']);
        $totalLiabilities = $this->calculateCategoryTotal($data['liabilities']);
        $totalEquity = $this->calculateCategoryTotal($data['equity']);

        $isBalanced = abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01;

        return [
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'total_liabilities_equity' => $totalLiabilities + $totalEquity,
            'is_balanced' => $isBalanced,
            'balance_difference' => $totalAssets - ($totalLiabilities + $totalEquity),
        ];
    }

    protected function getReportTitle(ReportParameterDTO $parameters): string
    {
        return "Neraca per {$parameters->endDate->format('d F Y')}";
    }

    /**
     * SECURITY FIX: Get account balances using secure query builder
     */
    private function getAccountBalances(\Carbon\Carbon $balanceDate, int $cooperativeId): array
    {
        return DB::table('accounts as a')
            ->leftJoin('journal_lines as jl', 'a.id', '=', 'jl.account_id')
            ->leftJoin('journal_entries as je', function ($join) use ($balanceDate) {
                $join->on('jl.journal_entry_id', '=', 'je.id')
                    ->where('je.is_approved', true)
                    ->where('je.transaction_date', '<=', $balanceDate);
            })
            ->select([
                'a.id',
                'a.code',
                'a.name',
                'a.type',
                'a.parent_id',
                'a.level',
                DB::raw('COALESCE(
                    SUM(
                        CASE
                            WHEN a.type IN (\'ASSET\', \'EXPENSE\')
                            THEN COALESCE(jl.debit_amount, 0) - COALESCE(jl.credit_amount, 0)
                            ELSE COALESCE(jl.credit_amount, 0) - COALESCE(jl.debit_amount, 0)
                        END
                    ), 0
                ) as balance')
            ])
            ->where('a.cooperative_id', $cooperativeId)
            ->groupBy(['a.id', 'a.code', 'a.name', 'a.type', 'a.parent_id', 'a.level'])
            ->orderBy('a.code')
            ->get()
            ->map(function ($account) {
                return (object) [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'parent_id' => $account->parent_id,
                    'level' => $account->level,
                    'balance' => (float) $account->balance,
                ];
            })
            ->toArray();
    }

    /**
     * Group accounts by category types
     */
    private function groupAccountsByCategory(array $accounts, array $types): array
    {
        return array_filter($accounts, function ($account) use ($types) {
            return in_array($account->type, $types);
        });
    }

    /**
     * PERFORMANCE FIX: Build hierarchical account structure efficiently
     */
    private function buildAccountHierarchy(array $accounts): array
    {
        if (empty($accounts)) {
            return [];
        }

        $hierarchy = [];
        $accountsById = [];
        $parentIds = [];

        // First pass: index all accounts and collect parent IDs
        foreach ($accounts as $account) {
            $accountData = (array) $account;
            $accountData['children'] = [];
            $accountsById[$account->id] = $accountData;

            if ($account->parent_id) {
                $parentIds[] = $account->parent_id;
            }
        }

        // Load missing parent accounts (even with zero balance)
        if (!empty($parentIds)) {
            $missingParents = array_diff($parentIds, array_keys($accountsById));
            if (!empty($missingParents)) {
                $parentAccounts = DB::table('accounts')
                    ->whereIn('id', $missingParents)
                    ->where('cooperative_id', app(\App\Infrastructure\Tenancy\TenantManager::class)->getCurrentTenantId())
                    ->get();

                foreach ($parentAccounts as $parent) {
                    if (!isset($accountsById[$parent->id])) {
                        $parentData = [
                            'id' => $parent->id,
                            'code' => $parent->code,
                            'name' => $parent->name,
                            'type' => $parent->type,
                            'parent_id' => $parent->parent_id,
                            'level' => $parent->level,
                            'balance' => 0,
                            'children' => [],
                        ];
                        $accountsById[$parent->id] = $parentData;
                    }
                }
            }
        }

        // Second pass: build hierarchy
        foreach ($accountsById as $id => $account) {
            if ($account['parent_id'] && isset($accountsById[$account['parent_id']])) {
                $accountsById[$account['parent_id']]['children'][] = &$accountsById[$id];
            } else {
                $hierarchy[] = &$accountsById[$id];
            }
        }

        // Third pass: calculate parent totals bottom-up
        $this->calculateParentTotalsOptimized($hierarchy);

        return $hierarchy;
    }

    /**
     * PERFORMANCE FIX: Optimized parent total calculation
     */
    private function calculateParentTotalsOptimized(array &$accounts): void
    {
        foreach ($accounts as &$account) {
            if (!empty($account['children'])) {
                $this->calculateParentTotalsOptimized($account['children']);
                $account['balance'] = array_sum(array_column($account['children'], 'balance'));
            }
        }
    }

    /**
     * Calculate total for account category
     */
    private function calculateCategoryTotal(array $accounts): float
    {
        $total = 0;
        foreach ($accounts as $account) {
            $total += $account['balance'];
        }
        return $total;
    }
}
