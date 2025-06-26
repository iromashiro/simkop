<?php
// app/Domain/Report/Reports/IncomeStatementReport.php
namespace App\Domain\Report\Reports;

use App\Domain\Report\Abstracts\BaseReport;
use App\Domain\Report\DTOs\ReportParameterDTO;
use Illuminate\Support\Facades\DB;

/**
 * Income Statement Report (Laporan Laba Rugi)
 * SRS Reference: Section 3.4.2 - Income Statement Requirements
 */
class IncomeStatementReport extends BaseReport
{
    protected string $reportCode = 'INCOME_STATEMENT';
    protected string $reportName = 'Laporan Laba Rugi';
    protected string $description = 'Laporan pendapatan dan beban dalam periode tertentu';
    protected array $requiredParameters = [];

    protected function generateReportData(ReportParameterDTO $parameters): array
    {
        // Get revenue and expense accounts with their balances
        $revenues = $this->getAccountBalancesByType('REVENUE', $parameters);
        $expenses = $this->getAccountBalancesByType('EXPENSE', $parameters);

        return [
            'period' => [
                'start_date' => $parameters->startDate->format('Y-m-d'),
                'end_date' => $parameters->endDate->format('Y-m-d'),
            ],
            'revenues' => $this->buildAccountHierarchy($revenues),
            'expenses' => $this->buildAccountHierarchy($expenses),
        ];
    }

    protected function generateSummary(array $data, ReportParameterDTO $parameters): array
    {
        $totalRevenues = $this->calculateCategoryTotal($data['revenues']);
        $totalExpenses = $this->calculateCategoryTotal($data['expenses']);
        $netIncome = $totalRevenues - $totalExpenses;

        return [
            'total_revenues' => $totalRevenues,
            'total_expenses' => $totalExpenses,
            'gross_profit' => $totalRevenues, // For cooperatives, usually same as total revenue
            'net_income' => $netIncome,
            'net_margin_percentage' => $totalRevenues > 0 ? ($netIncome / $totalRevenues) * 100 : 0,
        ];
    }

    protected function getReportTitle(ReportParameterDTO $parameters): string
    {
        return "Laporan Laba Rugi Periode {$parameters->startDate->format('d F Y')} s/d {$parameters->endDate->format('d F Y')}";
    }

    /**
     * Get account balances by type for the period
     */
    private function getAccountBalancesByType(string $type, ReportParameterDTO $parameters): array
    {
        $query = "
            SELECT
                a.id,
                a.code,
                a.name,
                a.type,
                a.parent_id,
                a.level,
                COALESCE(
                    SUM(
                        CASE
                            WHEN a.type = 'REVENUE'
                            THEN jl.credit_amount - jl.debit_amount
                            WHEN a.type = 'EXPENSE'
                            THEN jl.debit_amount - jl.credit_amount
                            ELSE 0
                        END
                    ), 0
                ) as balance
            FROM accounts a
            LEFT JOIN journal_lines jl ON a.id = jl.account_id
            LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id
            WHERE a.cooperative_id = ?
                AND a.type = ?
                AND (je.transaction_date IS NULL OR (je.transaction_date >= ? AND je.transaction_date <= ?))
                AND (je.is_approved IS NULL OR je.is_approved = true)
            GROUP BY a.id, a.code, a.name, a.type, a.parent_id, a.level
            HAVING COALESCE(
                SUM(
                    CASE
                        WHEN a.type = 'REVENUE'
                        THEN jl.credit_amount - jl.debit_amount
                        WHEN a.type = 'EXPENSE'
                        THEN jl.debit_amount - jl.credit_amount
                        ELSE 0
                    END
                ), 0
            ) != 0
            ORDER BY a.code
        ";

        return DB::select($query, [
            $parameters->cooperativeId,
            $type,
            $parameters->startDate->format('Y-m-d'),
            $parameters->endDate->format('Y-m-d')
        ]);
    }

    // Reuse hierarchy building methods from BalanceSheetReport
    private function buildAccountHierarchy(array $accounts): array
    {
        // Same implementation as BalanceSheetReport
        $hierarchy = [];
        $accountsById = [];

        foreach ($accounts as $account) {
            $accountsById[$account->id] = (array) $account;
            $accountsById[$account->id]['children'] = [];
        }

        foreach ($accountsById as $id => $account) {
            if ($account['parent_id']) {
                if (isset($accountsById[$account['parent_id']])) {
                    $accountsById[$account['parent_id']]['children'][] = &$accountsById[$id];
                }
            } else {
                $hierarchy[] = &$accountsById[$id];
            }
        }

        $this->calculateParentTotals($hierarchy);
        return $hierarchy;
    }

    private function calculateParentTotals(array &$accounts): void
    {
        foreach ($accounts as &$account) {
            if (!empty($account['children'])) {
                $this->calculateParentTotals($account['children']);
                $account['balance'] = array_sum(array_column($account['children'], 'balance'));
            }
        }
    }

    private function calculateCategoryTotal(array $accounts): float
    {
        $total = 0;
        foreach ($accounts as $account) {
            $total += $account['balance'];
        }
        return $total;
    }
}
