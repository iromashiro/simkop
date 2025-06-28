<?php

namespace App\Services\Financial;

use App\Models\Financial\FinancialReport;
use Illuminate\Support\Facades\Log;

class FinancialValidationService
{
    /**
     * Validate financial report data integrity.
     */
    public function validateReportIntegrity(FinancialReport $report): array
    {
        $errors = [];
        $warnings = [];

        try {
            switch ($report->report_type) {
                case 'balance_sheet':
                    $result = $this->validateBalanceSheet($report);
                    break;
                case 'income_statement':
                    $result = $this->validateIncomeStatement($report);
                    break;
                case 'cash_flow':
                    $result = $this->validateCashFlow($report);
                    break;
                case 'equity_changes':
                    $result = $this->validateEquityChanges($report);
                    break;
                case 'member_savings':
                    $result = $this->validateMemberSavings($report);
                    break;
                case 'member_receivables':
                    $result = $this->validateMemberReceivables($report);
                    break;
                case 'npl_receivables':
                    $result = $this->validateNPLReceivables($report);
                    break;
                case 'shu_distribution':
                    $result = $this->validateSHUDistribution($report);
                    break;
                case 'budget_plan':
                    $result = $this->validateBudgetPlan($report);
                    break;
                default:
                    $result = ['errors' => [], 'warnings' => []];
            }

            $errors = array_merge($errors, $result['errors']);
            $warnings = array_merge($warnings, $result['warnings']);

            // Cross-report validations
            $crossValidation = $this->validateCrossReportConsistency($report);
            $errors = array_merge($errors, $crossValidation['errors']);
            $warnings = array_merge($warnings, $crossValidation['warnings']);
        } catch (\Exception $e) {
            Log::error('Error validating financial report', [
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);
            $errors[] = 'Terjadi kesalahan dalam validasi laporan.';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'validation_summary' => $this->generateValidationSummary($errors, $warnings)
        ];
    }

    /**
     * Validate balance sheet.
     */
    private function validateBalanceSheet(FinancialReport $report): array
    {
        $errors = [];
        $warnings = [];

        $accounts = $report->balanceSheetAccounts;

        if ($accounts->isEmpty()) {
            $errors[] = 'Neraca harus memiliki minimal satu akun.';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Calculate totals
        $totalAssets = $accounts->where('account_category', 'asset')
            ->where('is_subtotal', false)
            ->sum('current_year_amount');

        $totalLiabilities = $accounts->where('account_category', 'liability')
            ->where('is_subtotal', false)
            ->sum('current_year_amount');

        $totalEquity = $accounts->where('account_category', 'equity')
            ->where('is_subtotal', false)
            ->sum('current_year_amount');

        // Balance sheet equation validation
        $difference = abs($totalAssets - ($totalLiabilities + $totalEquity));
        if ($difference > 1) {
            $errors[] = "Neraca tidak seimbang. Selisih: Rp " . number_format($difference, 2) .
                ". Total Aset: Rp " . number_format($totalAssets, 2) .
                ", Total Kewajiban + Ekuitas: Rp " . number_format($totalLiabilities + $totalEquity, 2);
        }

        // Check for required account categories
        $hasAssets = $accounts->where('account_category', 'asset')->isNotEmpty();
        $hasLiabilities = $accounts->where('account_category', 'liability')->isNotEmpty();
        $hasEquity = $accounts->where('account_category', 'equity')->isNotEmpty();

        if (!$hasAssets) {
            $errors[] = 'Neraca harus memiliki minimal satu akun aset.';
        }
        if (!$hasLiabilities) {
            $warnings[] = 'Neraca tidak memiliki akun kewajiban.';
        }
        if (!$hasEquity) {
            $errors[] = 'Neraca harus memiliki minimal satu akun ekuitas.';
        }

        // Check for negative amounts in inappropriate accounts
        $negativeAssets = $accounts->where('account_category', 'asset')
            ->where('current_year_amount', '<', 0);
        if ($negativeAssets->isNotEmpty()) {
            $warnings[] = 'Terdapat akun aset dengan nilai negatif: ' .
                $negativeAssets->pluck('account_name')->implode(', ');
        }

        // Check for duplicate account codes
        $duplicateCodes = $accounts->groupBy('account_code')
            ->filter(function ($group) {
                return $group->count() > 1;
            })
            ->keys();
        if ($duplicateCodes->isNotEmpty()) {
            $errors[] = 'Terdapat kode akun duplikat: ' . $duplicateCodes->implode(', ');
        }

        // Validate parent-child relationships
        $parentChildErrors = $this->validateParentChildRelationships($accounts);
        $errors = array_merge($errors, $parentChildErrors);

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate income statement.
     */
    private function validateIncomeStatement(FinancialReport $report): array
    {
        $errors = [];
        $warnings = [];

        $accounts = $report->incomeStatementAccounts;

        if ($accounts->isEmpty()) {
            $errors[] = 'Laporan laba rugi harus memiliki minimal satu akun.';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Check for required account categories
        $hasRevenue = $accounts->where('account_category', 'revenue')->isNotEmpty();
        $hasExpense = $accounts->where('account_category', 'expense')->isNotEmpty();

        if (!$hasRevenue) {
            $errors[] = 'Laporan laba rugi harus memiliki minimal satu akun pendapatan.';
        }
        if (!$hasExpense) {
            $warnings[] = 'Laporan laba rugi tidak memiliki akun beban.';
        }

        // Calculate totals
        $totalRevenue = $accounts->where('account_category', 'revenue')
            ->where('is_subtotal', false)
            ->sum('current_year_amount');

        $totalExpenses = $accounts->where('account_category', 'expense')
            ->where('is_subtotal', false)
            ->sum('current_year_amount');

        $netIncome = $totalRevenue - $totalExpenses;

        // Check for unusual patterns
        if ($totalRevenue <= 0) {
            $warnings[] = 'Total pendapatan tidak ada atau negatif.';
        }

        if ($totalExpenses <= 0) {
            $warnings[] = 'Total beban tidak ada atau negatif.';
        }

        if ($netIncome < 0) {
            $warnings[] = 'Koperasi mengalami kerugian sebesar Rp ' . number_format(abs($netIncome), 2);
        }

        // Check expense ratio
        if ($totalRevenue > 0) {
            $expenseRatio = ($totalExpenses / $totalRevenue) * 100;
            if ($expenseRatio > 90) {
                $warnings[] = "Rasio beban terhadap pendapatan tinggi: {$expenseRatio}%";
            }
        }

        // Check for duplicate account codes
        $duplicateCodes = $accounts->groupBy('account_code')
            ->filter(function ($group) {
                return $group->count() > 1;
            })
            ->keys();
        if ($duplicateCodes->isNotEmpty()) {
            $errors[] = 'Terdapat kode akun duplikat: ' . $duplicateCodes->implode(', ');
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate cash flow.
     */
    private function validateCashFlow(FinancialReport $report): array
    {
        $errors = [];
        $warnings = [];

        $activities = $report->cashFlowActivities;

        if ($activities->isEmpty()) {
            $errors[] = 'Laporan arus kas harus memiliki minimal satu aktivitas.';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Check for required activity categories
        $hasOperating = $activities->where('activity_category', 'operating')->isNotEmpty();
        if (!$hasOperating) {
            $errors[] = 'Laporan arus kas harus memiliki minimal satu aktivitas operasi.';
        }

        // Calculate cash flow totals
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

        // Validate cash balance consistency
        $beginningBalance = $report->data['beginning_cash_balance'] ?? 0;
        $endingBalance = $report->data['ending_cash_balance'] ?? 0;
        $calculatedEndingBalance = $beginningBalance + $netCashFlow;

        $difference = abs($endingBalance - $calculatedEndingBalance);
        if ($difference > 1) {
            $errors[] = "Saldo kas akhir tidak konsisten. Selisih: Rp " . number_format($difference, 2);
        }

        // Check for unusual patterns
        if ($operatingCashFlow < 0) {
            $warnings[] = 'Arus kas operasi negatif sebesar Rp ' . number_format(abs($operatingCashFlow), 2);
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate equity changes.
     */
    private function validateEquityChanges(FinancialReport $report): array
    {
        $errors = [];
        $warnings = [];

        $equityChanges = $report->equityChanges;

        if ($equityChanges->isEmpty()) {
            $errors[] = 'Laporan perubahan ekuitas harus memiliki minimal satu komponen.';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Validate each equity change calculation
        foreach ($equityChanges as $change) {
            $calculatedEndingBalance = $change->beginning_balance + $change->additions - $change->reductions;
            $difference = abs($change->ending_balance - $calculatedEndingBalance);

            if ($difference > 1) {
                $errors[] = "Perhitungan saldo akhir tidak benar untuk komponen {$change->equity_component}. " .
                    "Selisih: Rp " . number_format($difference, 2);
            }
        }

        // Check for duplicate components
        $duplicateComponents = $equityChanges->groupBy('equity_component')
            ->filter(function ($group) {
                return $group->count() > 1;
            })
            ->keys();
        if ($duplicateComponents->isNotEmpty()) {
            $errors[] = 'Terdapat komponen ekuitas duplikat: ' . $duplicateComponents->implode(', ');
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate member savings.
     */
    private function validateMemberSavings(FinancialReport $report): array
    {
        $errors = [];
        $warnings = [];

        $memberSavings = $report->memberSavings;

        if ($memberSavings->isEmpty()) {
            $errors[] = 'Laporan simpanan anggota harus memiliki minimal satu data.';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Validate each member savings calculation
        foreach ($memberSavings as $saving) {
            $calculatedEndingBalance = $saving->beginning_balance + $saving->deposits - $saving->withdrawals + $saving->interest_earned;
            $difference = abs($saving->ending_balance - $calculatedEndingBalance);

            if ($difference > 1) {
                $errors[] = "Perhitungan saldo akhir tidak benar untuk anggota {$saving->member_name}. " .
                    "Selisih: Rp " . number_format($difference, 2);
            }
        }

        // Check for duplicate member-savings type combinations
        $duplicateKeys = $memberSavings->groupBy(function ($item) {
            return $item->member_id . '|' . $item->savings_type;
        })->filter(function ($group) {
            return $group->count() > 1;
        })->keys();

        if ($duplicateKeys->isNotEmpty()) {
            $errors[] = 'Terdapat duplikasi data simpanan untuk anggota yang sama.';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate member receivables.
     */
    private function validateMemberReceivables(FinancialReport $report): array
    {
        $errors = [];
        $warnings = [];

        $memberReceivables = $report->memberReceivables;

        if ($memberReceivables->isEmpty()) {
            $warnings[] = 'Laporan piutang anggota tidak memiliki data.';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        foreach ($memberReceivables as $receivable) {
            // Validate outstanding balance vs loan amount
            if ($receivable->outstanding_balance > $receivable->loan_amount) {
                $errors[] = "Saldo piutang anggota {$receivable->member_name} melebihi jumlah kredit.";
            }

            // Validate maturity date vs disbursement date
            if ($receivable->maturity_date <= $receivable->disbursement_date) {
                $errors[] = "Tanggal jatuh tempo kredit anggota {$receivable->member_name} tidak valid.";
            }

            // Check for overdue loans
            if ($receivable->payment_status !== 'current' && $receivable->outstanding_balance > 0) {
                $warnings[] = "Anggota {$receivable->member_name} memiliki tunggakan.";
            }
        }

        // Check for duplicate loan numbers
        $duplicateLoanNumbers = $memberReceivables->groupBy('loan_number')
            ->filter(function ($group) {
                return $group->count() > 1;
            })
            ->keys();
        if ($duplicateLoanNumbers->isNotEmpty()) {
            $errors[] = 'Terdapat nomor kredit duplikat: ' . $duplicateLoanNumbers->implode(', ');
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate NPL receivables.
     */
    private function validateNPLReceivables(FinancialReport $report): array
    {
        $errors = [];
        $warnings = [];

        $nplReceivables = $report->nonPerformingReceivables;

        if ($nplReceivables->isEmpty()) {
            $warnings[] = 'Tidak ada data piutang NPL.';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        foreach ($nplReceivables as $npl) {
            // Validate NPL classification vs days past due
            $classification = $npl->npl_classification;
            $daysPastDue = $npl->days_past_due;

            if ($daysPastDue >= 91 && $daysPastDue <= 120 && $classification !== 'kurang_lancar') {
                $errors[] = "Klasifikasi NPL anggota {$npl->member_name} tidak sesuai dengan hari tunggakan.";
            } elseif ($daysPastDue >= 121 && $daysPastDue <= 180 && $classification !== 'diragukan') {
                $errors[] = "Klasifikasi NPL anggota {$npl->member_name} tidak sesuai dengan hari tunggakan.";
            } elseif ($daysPastDue > 180 && $classification !== 'macet') {
                $errors[] = "Klasifikasi NPL anggota {$npl->member_name} tidak sesuai dengan hari tunggakan.";
            }

            // Validate provision calculation
            $calculatedProvision = $npl->outstanding_balance * ($npl->provision_percentage / 100);
            $difference = abs($npl->provision_amount - $calculatedProvision);

            if ($difference > 1) {
                $errors[] = "Perhitungan penyisihan tidak benar untuk anggota {$npl->member_name}. " .
                    "Selisih: Rp " . number_format($difference, 2);
            }

            // Validate minimum provision percentage
            $minProvision = match ($classification) {
                'kurang_lancar' => 10,
                'diragukan' => 50,
                'macet' => 100,
                default => 0
            };

            if ($npl->provision_percentage < $minProvision) {
                $errors[] = "Persentase penyisihan anggota {$npl->member_name} kurang dari minimum {$minProvision}%.";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate SHU distribution.
     */
    private function validateSHUDistribution(FinancialReport $report): array
    {
        $errors = [];
        $warnings = [];

        $shuDistributions = $report->shuDistributions;

        if ($shuDistributions->isEmpty()) {
            $errors[] = 'Laporan distribusi SHU harus memiliki minimal satu data.';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $totalShu = $report->data['total_shu'] ?? 0;
        $totalDistributed = 0;

        foreach ($shuDistributions as $distribution) {
            // Validate SHU calculation
            $calculatedTotalShu = $distribution->shu_from_savings + $distribution->shu_from_transactions;
            $difference = abs($distribution->total_shu_received - $calculatedTotalShu);

            if ($difference > 1) {
                $errors[] = "Perhitungan total SHU tidak benar untuk anggota {$distribution->member_name}. " .
                    "Selisih: Rp " . number_format($difference, 2);
            }

            // Validate net SHU calculation
            $calculatedNetShu = $distribution->total_shu_received - $distribution->tax_deduction;
            $netDifference = abs($distribution->net_shu_received - $calculatedNetShu);

            if ($netDifference > 1) {
                $errors[] = "Perhitungan SHU bersih tidak benar untuk anggota {$distribution->member_name}. " .
                    "Selisih: Rp " . number_format($netDifference, 2);
            }

            $totalDistributed += $distribution->total_shu_received;
        }

        // Validate total SHU distribution
        $totalDifference = abs($totalShu - $totalDistributed);
        if ($totalDifference > 10) {
            $errors[] = "Total SHU tidak sesuai dengan jumlah distribusi. " .
                "Selisih: Rp " . number_format($totalDifference, 2);
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate budget plan.
     */
    private function validateBudgetPlan(FinancialReport $report): array
    {
        $errors = [];
        $warnings = [];

        $budgetPlans = $report->budgetPlans;

        if ($budgetPlans->isEmpty()) {
            $errors[] = 'Rencana anggaran harus memiliki minimal satu item.';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $totalRevenue = $budgetPlans->where('budget_category', 'revenue')->sum('planned_amount');
        $totalExpense = $budgetPlans->where('budget_category', 'expense')->sum('planned_amount');

        // Check budget balance
        if ($totalExpense > $totalRevenue * 1.2) {
            $warnings[] = 'Total beban melebihi 120% dari total pendapatan yang direncanakan.';
        }

        // Validate quarterly allocations
        foreach ($budgetPlans as $plan) {
            $totalAllocation = $plan->quarter_1_allocation + $plan->quarter_2_allocation +
                $plan->quarter_3_allocation + $plan->quarter_4_allocation;

            if ($totalAllocation > 0 && abs($totalAllocation - 100) > 0.01) {
                $warnings[] = "Total alokasi kuartalan untuk item '{$plan->budget_item}' tidak 100%.";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate cross-report consistency.
     */
    private function validateCrossReportConsistency(FinancialReport $report): array
    {
        $errors = [];
        $warnings = [];

        // Get other reports for the same cooperative and year
        $otherReports = FinancialReport::where('cooperative_id', $report->cooperative_id)
            ->where('reporting_year', $report->reporting_year)
            ->where('id', '!=', $report->id)
            ->where('status', 'approved')
            ->get();

        if ($otherReports->isEmpty()) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Cross-validate balance sheet with income statement
        $balanceSheet = $otherReports->where('report_type', 'balance_sheet')->first();
        $incomeStatement = $otherReports->where('report_type', 'income_statement')->first();

        if ($balanceSheet && $incomeStatement && $report->report_type === 'balance_sheet') {
            $this->validateBalanceSheetIncomeStatementConsistency($report, $incomeStatement, $errors, $warnings);
        }

        // Cross-validate cash flow with balance sheet
        $cashFlow = $otherReports->where('report_type', 'cash_flow')->first();
        if ($balanceSheet && $cashFlow && $report->report_type === 'cash_flow') {
            $this->validateCashFlowBalanceSheetConsistency($report, $balanceSheet, $errors, $warnings);
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate balance sheet and income statement consistency.
     */
    private function validateBalanceSheetIncomeStatementConsistency(
        FinancialReport $balanceSheet,
        FinancialReport $incomeStatement,
        array &$errors,
        array &$warnings
    ): void {
        // Get retained earnings from balance sheet
        $retainedEarnings = $balanceSheet->balanceSheetAccounts
            ->where('account_category', 'equity')
            ->where('account_name', 'like', '%laba%ditahan%')
            ->sum('current_year_amount');

        // Get net income from income statement
        $totalRevenue = $incomeStatement->incomeStatementAccounts
            ->where('account_category', 'revenue')
            ->sum('current_year_amount');
        $totalExpenses = $incomeStatement->incomeStatementAccounts
            ->where('account_category', 'expense')
            ->sum('current_year_amount');
        $netIncome = $totalRevenue - $totalExpenses;

        // This is a simplified check - in practice, you'd need to consider
        // previous year retained earnings and dividends paid
        if (abs($retainedEarnings - $netIncome) > 1000000) { // Allow 1M difference
            $warnings[] = 'Laba ditahan di neraca tidak konsisten dengan laba bersih di laporan laba rugi.';
        }
    }

    /**
     * Validate cash flow and balance sheet consistency.
     */
    private function validateCashFlowBalanceSheetConsistency(
        FinancialReport $cashFlow,
        FinancialReport $balanceSheet,
        array &$errors,
        array &$warnings
    ): void {
        // Get cash and cash equivalents from balance sheet
        $cashFromBalanceSheet = $balanceSheet->balanceSheetAccounts
            ->where('account_category', 'asset')
            ->where('account_name', 'like', '%kas%')
            ->sum('current_year_amount');

        // Get ending cash balance from cash flow
        $endingCashFromCashFlow = $cashFlow->data['ending_cash_balance'] ?? 0;

        $difference = abs($cashFromBalanceSheet - $endingCashFromCashFlow);
        if ($difference > 1) {
            $errors[] = "Saldo kas di neraca tidak konsisten dengan saldo kas akhir di laporan arus kas. " .
                "Selisih: Rp " . number_format($difference, 2);
        }
    }

    /**
     * Validate parent-child relationships.
     */
    private function validateParentChildRelationships($accounts): array
    {
        $errors = [];
        $accountCodes = $accounts->pluck('account_code')->toArray();

        foreach ($accounts as $account) {
            if ($account->parent_account_code) {
                if (!in_array($account->parent_account_code, $accountCodes)) {
                    $errors[] = "Akun induk '{$account->parent_account_code}' untuk akun '{$account->account_name}' tidak ditemukan.";
                }

                if ($account->parent_account_code === $account->account_code) {
                    $errors[] = "Akun '{$account->account_name}' tidak boleh menjadi induk dari dirinya sendiri.";
                }
            }
        }

        return $errors;
    }

    /**
     * Generate validation summary.
     */
    private function generateValidationSummary(array $errors, array $warnings): array
    {
        return [
            'total_errors' => count($errors),
            'total_warnings' => count($warnings),
            'validation_status' => empty($errors) ? 'passed' : 'failed',
            'severity_level' => $this->determineSeverityLevel($errors, $warnings),
            'validated_at' => now()->toISOString(),
            'validated_by' => auth()->user()->name ?? 'system'
        ];
    }

    /**
     * Determine severity level based on errors and warnings.
     */
    private function determineSeverityLevel(array $errors, array $warnings): string
    {
        if (count($errors) > 5) {
            return 'critical';
        } elseif (count($errors) > 0) {
            return 'high';
        } elseif (count($warnings) > 10) {
            return 'medium';
        } elseif (count($warnings) > 0) {
            return 'low';
        } else {
            return 'none';
        }
    }

    /**
     * Get validation rules for specific report type.
     */
    public function getValidationRules(string $reportType): array
    {
        return match ($reportType) {
            'balance_sheet' => [
                'balance_equation' => 'Assets = Liabilities + Equity',
                'required_categories' => ['asset', 'equity'],
                'optional_categories' => ['liability'],
                'no_duplicate_codes' => true,
                'parent_child_validation' => true
            ],
            'income_statement' => [
                'required_categories' => ['revenue'],
                'optional_categories' => ['expense', 'other_income', 'other_expense'],
                'no_duplicate_codes' => true,
                'parent_child_validation' => true
            ],
            'cash_flow' => [
                'required_categories' => ['operating'],
                'optional_categories' => ['investing', 'financing'],
                'cash_balance_consistency' => true
            ],
            'equity_changes' => [
                'calculation_validation' => true,
                'no_duplicate_components' => true
            ],
            'member_savings' => [
                'calculation_validation' => true,
                'no_duplicate_members' => true
            ],
            'member_receivables' => [
                'outstanding_vs_loan_amount' => true,
                'date_validation' => true,
                'no_duplicate_loan_numbers' => true
            ],
            'npl_receivables' => [
                'classification_validation' => true,
                'provision_calculation' => true,
                'minimum_provision_percentage' => true
            ],
            'shu_distribution' => [
                'calculation_validation' => true,
                'total_distribution_validation' => true,
                'no_duplicate_members' => true
            ],
            'budget_plan' => [
                'quarterly_allocation_validation' => true,
                'budget_balance_check' => true
            ],
            default => []
        };
    }
}
