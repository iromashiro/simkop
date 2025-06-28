<?php

namespace App\Services\Financial;

use App\Models\Financial\FinancialReport;
use App\Models\Cooperative;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportGenerationService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Generate a new financial report.
     */
    public function generateReport(array $data): FinancialReport
    {
        return DB::transaction(function () use ($data) {
            try {
                // Create the main financial report
                $report = FinancialReport::create([
                    'cooperative_id' => $data['cooperative_id'],
                    'report_type' => $data['report_type'],
                    'reporting_year' => $data['reporting_year'],
                    'reporting_period' => $data['reporting_period'],
                    'status' => $data['status'] ?? 'draft',
                    'notes' => $data['notes'] ?? null,
                    'data' => $this->prepareReportData($data),
                    'created_by' => auth()->id(),
                ]);

                // Generate related data based on report type
                $this->generateRelatedData($report, $data);

                // Log the generation
                $this->auditLogService->log(
                    'report_generated',
                    "Financial report {$report->report_type} generated",
                    [
                        'report_id' => $report->id,
                        'cooperative_id' => $report->cooperative_id,
                        'reporting_year' => $report->reporting_year,
                        'report_type' => $report->report_type
                    ]
                );

                return $report->load($this->getRelationshipsForReportType($report->report_type));
            } catch (\Exception $e) {
                Log::error('Error generating financial report', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                    'user_id' => auth()->id()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Update an existing financial report.
     */
    public function updateReport(FinancialReport $report, array $data): FinancialReport
    {
        return DB::transaction(function () use ($report, $data) {
            try {
                $oldStatus = $report->status;

                // Update the main report
                $report->update([
                    'reporting_year' => $data['reporting_year'] ?? $report->reporting_year,
                    'reporting_period' => $data['reporting_period'] ?? $report->reporting_period,
                    'status' => $data['status'] ?? $report->status,
                    'notes' => $data['notes'] ?? $report->notes,
                    'data' => $this->prepareReportData($data),
                    'updated_by' => auth()->id(),
                ]);

                // Update related data
                $this->updateRelatedData($report, $data);

                // Log the update
                $this->auditLogService->log(
                    'report_updated',
                    "Financial report {$report->report_type} updated",
                    [
                        'report_id' => $report->id,
                        'old_status' => $oldStatus,
                        'new_status' => $report->status,
                        'changes' => $report->getChanges()
                    ]
                );

                return $report->load($this->getRelationshipsForReportType($report->report_type));
            } catch (\Exception $e) {
                Log::error('Error updating financial report', [
                    'error' => $e->getMessage(),
                    'report_id' => $report->id,
                    'data' => $data,
                    'user_id' => auth()->id()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Duplicate a financial report for a new period.
     */
    public function duplicateReport(FinancialReport $sourceReport, int $newYear, string $newPeriod): FinancialReport
    {
        return DB::transaction(function () use ($sourceReport, $newYear, $newPeriod) {
            try {
                // Create new report
                $newReport = $sourceReport->replicate();
                $newReport->reporting_year = $newYear;
                $newReport->reporting_period = $newPeriod;
                $newReport->status = 'draft';
                $newReport->created_by = auth()->id();
                $newReport->updated_by = null;
                $newReport->submitted_at = null;
                $newReport->approved_at = null;
                $newReport->approved_by = null;
                $newReport->save();

                // Duplicate related data with zero amounts
                $this->duplicateRelatedData($sourceReport, $newReport);

                // Log the duplication
                $this->auditLogService->log(
                    'report_duplicated',
                    "Financial report duplicated from {$sourceReport->reporting_year} to {$newYear}",
                    [
                        'source_report_id' => $sourceReport->id,
                        'new_report_id' => $newReport->id,
                        'new_year' => $newYear,
                        'new_period' => $newPeriod
                    ]
                );

                return $newReport->load($this->getRelationshipsForReportType($newReport->report_type));
            } catch (\Exception $e) {
                Log::error('Error duplicating financial report', [
                    'error' => $e->getMessage(),
                    'source_report_id' => $sourceReport->id,
                    'new_year' => $newYear,
                    'user_id' => auth()->id()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Generate consolidated report for multiple cooperatives.
     */
    public function generateConsolidatedReport(array $cooperativeIds, string $reportType, int $year, string $period): array
    {
        try {
            $reports = FinancialReport::whereIn('cooperative_id', $cooperativeIds)
                ->where('report_type', $reportType)
                ->where('reporting_year', $year)
                ->where('reporting_period', $period)
                ->where('status', 'approved')
                ->with(['cooperative', $this->getRelationshipsForReportType($reportType)])
                ->get();

            $consolidatedData = $this->consolidateReportData($reports, $reportType);

            // Log the consolidation
            $this->auditLogService->log(
                'consolidated_report_generated',
                "Consolidated {$reportType} report generated",
                [
                    'cooperative_ids' => $cooperativeIds,
                    'report_type' => $reportType,
                    'year' => $year,
                    'period' => $period,
                    'report_count' => $reports->count()
                ]
            );

            return [
                'reports' => $reports,
                'consolidated_data' => $consolidatedData,
                'summary' => $this->generateConsolidationSummary($reports, $reportType)
            ];
        } catch (\Exception $e) {
            Log::error('Error generating consolidated report', [
                'error' => $e->getMessage(),
                'cooperative_ids' => $cooperativeIds,
                'report_type' => $reportType,
                'user_id' => auth()->id()
            ]);
            throw $e;
        }
    }

    /**
     * Prepare report data for storage.
     */
    private function prepareReportData(array $data): array
    {
        $reportData = [];

        // Add metadata
        $reportData['generated_at'] = now()->toISOString();
        $reportData['generated_by'] = auth()->user()->name;
        $reportData['version'] = '1.0';

        // Add summary calculations based on report type
        if (isset($data['accounts'])) {
            $reportData['summary'] = $this->calculateAccountSummary($data['accounts']);
        }

        if (isset($data['activities'])) {
            $reportData['summary'] = $this->calculateActivitySummary($data['activities']);
        }

        if (isset($data['equity_changes'])) {
            $reportData['summary'] = $this->calculateEquitySummary($data['equity_changes']);
        }

        return $reportData;
    }

    /**
     * Generate related data based on report type.
     */
    private function generateRelatedData(FinancialReport $report, array $data): void
    {
        switch ($report->report_type) {
            case 'balance_sheet':
                $this->generateBalanceSheetAccounts($report, $data['accounts'] ?? []);
                break;

            case 'income_statement':
                $this->generateIncomeStatementAccounts($report, $data['accounts'] ?? []);
                break;

            case 'equity_changes':
                $this->generateEquityChanges($report, $data['equity_changes'] ?? []);
                break;

            case 'cash_flow':
                $this->generateCashFlowActivities($report, $data['activities'] ?? []);
                break;

            case 'member_savings':
                $this->generateMemberSavings($report, $data['member_savings'] ?? []);
                break;

            case 'member_receivables':
                $this->generateMemberReceivables($report, $data['member_receivables'] ?? []);
                break;

            case 'npl_receivables':
                $this->generateNPLReceivables($report, $data['npl_receivables'] ?? []);
                break;

            case 'shu_distribution':
                $this->generateSHUDistributions($report, $data['shu_distributions'] ?? []);
                break;

            case 'budget_plan':
                $this->generateBudgetPlans($report, $data['budget_plans'] ?? []);
                break;
        }
    }

    /**
     * Update related data for existing report.
     */
    private function updateRelatedData(FinancialReport $report, array $data): void
    {
        // Delete existing related data
        $this->deleteRelatedData($report);

        // Generate new related data
        $this->generateRelatedData($report, $data);
    }

    /**
     * Delete related data for a report.
     */
    private function deleteRelatedData(FinancialReport $report): void
    {
        switch ($report->report_type) {
            case 'balance_sheet':
                $report->balanceSheetAccounts()->delete();
                break;

            case 'income_statement':
                $report->incomeStatementAccounts()->delete();
                break;

            case 'equity_changes':
                $report->equityChanges()->delete();
                break;

            case 'cash_flow':
                $report->cashFlowActivities()->delete();
                break;

            case 'member_savings':
                $report->memberSavings()->delete();
                break;

            case 'member_receivables':
                $report->memberReceivables()->delete();
                break;

            case 'npl_receivables':
                $report->nonPerformingReceivables()->delete();
                break;

            case 'shu_distribution':
                $report->shuDistributions()->delete();
                break;

            case 'budget_plan':
                $report->budgetPlans()->delete();
                break;
        }
    }

    /**
     * Duplicate related data with zero amounts.
     */
    private function duplicateRelatedData(FinancialReport $sourceReport, FinancialReport $newReport): void
    {
        switch ($sourceReport->report_type) {
            case 'balance_sheet':
                foreach ($sourceReport->balanceSheetAccounts as $account) {
                    $newAccount = $account->replicate();
                    $newAccount->financial_report_id = $newReport->id;
                    $newAccount->current_year_amount = 0;
                    $newAccount->previous_year_amount = $account->current_year_amount;
                    $newAccount->save();
                }
                break;

            case 'income_statement':
                foreach ($sourceReport->incomeStatementAccounts as $account) {
                    $newAccount = $account->replicate();
                    $newAccount->financial_report_id = $newReport->id;
                    $newAccount->current_year_amount = 0;
                    $newAccount->previous_year_amount = $account->current_year_amount;
                    $newAccount->save();
                }
                break;

                // Add other report types as needed
        }
    }

    /**
     * Generate balance sheet accounts.
     */
    private function generateBalanceSheetAccounts(FinancialReport $report, array $accounts): void
    {
        foreach ($accounts as $accountData) {
            $report->balanceSheetAccounts()->create([
                'account_code' => $accountData['account_code'],
                'account_name' => $accountData['account_name'],
                'account_category' => $accountData['account_category'],
                'account_subcategory' => $accountData['account_subcategory'] ?? null,
                'current_year_amount' => $accountData['current_year_amount'],
                'previous_year_amount' => $accountData['previous_year_amount'] ?? 0,
                'note_reference' => $accountData['note_reference'] ?? null,
                'is_subtotal' => $accountData['is_subtotal'] ?? false,
                'parent_account_code' => $accountData['parent_account_code'] ?? null,
                'sort_order' => $accountData['sort_order'] ?? 0,
            ]);
        }
    }

    /**
     * Generate income statement accounts.
     */
    private function generateIncomeStatementAccounts(FinancialReport $report, array $accounts): void
    {
        foreach ($accounts as $accountData) {
            $report->incomeStatementAccounts()->create([
                'account_code' => $accountData['account_code'],
                'account_name' => $accountData['account_name'],
                'account_category' => $accountData['account_category'],
                'account_subcategory' => $accountData['account_subcategory'] ?? null,
                'current_year_amount' => $accountData['current_year_amount'],
                'previous_year_amount' => $accountData['previous_year_amount'] ?? 0,
                'note_reference' => $accountData['note_reference'] ?? null,
                'is_subtotal' => $accountData['is_subtotal'] ?? false,
                'parent_account_code' => $accountData['parent_account_code'] ?? null,
                'sort_order' => $accountData['sort_order'] ?? 0,
            ]);
        }
    }

    /**
     * Generate equity changes.
     */
    private function generateEquityChanges(FinancialReport $report, array $equityChanges): void
    {
        foreach ($equityChanges as $changeData) {
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
    }

    /**
     * Generate cash flow activities.
     */
    private function generateCashFlowActivities(FinancialReport $report, array $activities): void
    {
        foreach ($activities as $activityData) {
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
    }

    /**
     * Generate member savings.
     */
    private function generateMemberSavings(FinancialReport $report, array $memberSavings): void
    {
        foreach ($memberSavings as $savingData) {
            $report->memberSavings()->create([
                'member_id' => $savingData['member_id'],
                'member_name' => $savingData['member_name'],
                'savings_type' => $savingData['savings_type'],
                'beginning_balance' => $savingData['beginning_balance'],
                'deposits' => $savingData['deposits'] ?? 0,
                'withdrawals' => $savingData['withdrawals'] ?? 0,
                'ending_balance' => $savingData['ending_balance'],
                'interest_earned' => $savingData['interest_earned'] ?? 0,
                'note_reference' => $savingData['note_reference'] ?? null,
            ]);
        }
    }

    /**
     * Generate member receivables.
     */
    private function generateMemberReceivables(FinancialReport $report, array $memberReceivables): void
    {
        foreach ($memberReceivables as $receivableData) {
            $report->memberReceivables()->create([
                'member_id' => $receivableData['member_id'],
                'member_name' => $receivableData['member_name'],
                'loan_type' => $receivableData['loan_type'],
                'loan_number' => $receivableData['loan_number'],
                'loan_amount' => $receivableData['loan_amount'],
                'outstanding_balance' => $receivableData['outstanding_balance'],
                'interest_rate' => $receivableData['interest_rate'],
                'loan_term_months' => $receivableData['loan_term_months'],
                'disbursement_date' => $receivableData['disbursement_date'],
                'maturity_date' => $receivableData['maturity_date'],
                'payment_status' => $receivableData['payment_status'],
                'collateral_type' => $receivableData['collateral_type'] ?? null,
                'collateral_value' => $receivableData['collateral_value'] ?? 0,
                'note_reference' => $receivableData['note_reference'] ?? null,
            ]);
        }
    }

    /**
     * Generate NPL receivables.
     */
    private function generateNPLReceivables(FinancialReport $report, array $nplReceivables): void
    {
        foreach ($nplReceivables as $nplData) {
            $report->nonPerformingReceivables()->create([
                'member_id' => $nplData['member_id'],
                'member_name' => $nplData['member_name'],
                'loan_number' => $nplData['loan_number'],
                'original_loan_amount' => $nplData['original_loan_amount'],
                'outstanding_balance' => $nplData['outstanding_balance'],
                'days_past_due' => $nplData['days_past_due'],
                'npl_classification' => $nplData['npl_classification'],
                'provision_percentage' => $nplData['provision_percentage'],
                'provision_amount' => $nplData['provision_amount'],
                'collateral_type' => $nplData['collateral_type'] ?? null,
                'collateral_value' => $nplData['collateral_value'] ?? 0,
                'recovery_efforts' => $nplData['recovery_efforts'] ?? null,
                'last_payment_date' => $nplData['last_payment_date'] ?? null,
                'restructuring_status' => $nplData['restructuring_status'] ?? 'none',
                'write_off_status' => $nplData['write_off_status'] ?? 'none',
                'note_reference' => $nplData['note_reference'] ?? null,
            ]);
        }
    }

    /**
     * Generate SHU distributions.
     */
    private function generateSHUDistributions(FinancialReport $report, array $shuDistributions): void
    {
        foreach ($shuDistributions as $distributionData) {
            $report->shuDistributions()->create([
                'member_id' => $distributionData['member_id'],
                'member_name' => $distributionData['member_name'],
                'member_type' => $distributionData['member_type'],
                'savings_contribution' => $distributionData['savings_contribution'],
                'transaction_contribution' => $distributionData['transaction_contribution'],
                'shu_from_savings' => $distributionData['shu_from_savings'],
                'shu_from_transactions' => $distributionData['shu_from_transactions'],
                'total_shu_received' => $distributionData['total_shu_received'],
                'tax_deduction' => $distributionData['tax_deduction'] ?? 0,
                'net_shu_received' => $distributionData['net_shu_received'],
                'payment_method' => $distributionData['payment_method'],
                'payment_status' => $distributionData['payment_status'],
                'note_reference' => $distributionData['note_reference'] ?? null,
            ]);
        }
    }

    /**
     * Generate budget plans.
     */
    private function generateBudgetPlans(FinancialReport $report, array $budgetPlans): void
    {
        foreach ($budgetPlans as $planData) {
            $report->budgetPlans()->create([
                'budget_category' => $planData['budget_category'],
                'budget_subcategory' => $planData['budget_subcategory'],
                'budget_item' => $planData['budget_item'],
                'budget_description' => $planData['budget_description'] ?? null,
                'planned_amount' => $planData['planned_amount'],
                'previous_year_actual' => $planData['previous_year_actual'] ?? 0,
                'variance_percentage' => $planData['variance_percentage'] ?? 0,
                'priority_level' => $planData['priority_level'],
                'quarter_1_allocation' => $planData['quarter_1_allocation'] ?? 0,
                'quarter_2_allocation' => $planData['quarter_2_allocation'] ?? 0,
                'quarter_3_allocation' => $planData['quarter_3_allocation'] ?? 0,
                'quarter_4_allocation' => $planData['quarter_4_allocation'] ?? 0,
                'responsible_department' => $planData['responsible_department'] ?? null,
                'approval_required' => $planData['approval_required'] ?? false,
                'note_reference' => $planData['note_reference'] ?? null,
                'sort_order' => $planData['sort_order'] ?? 0,
            ]);
        }
    }

    /**
     * Calculate account summary.
     */
    private function calculateAccountSummary(array $accounts): array
    {
        $summary = [
            'total_assets' => 0,
            'total_liabilities' => 0,
            'total_equity' => 0,
            'total_revenue' => 0,
            'total_expenses' => 0,
            'account_count' => count($accounts)
        ];

        foreach ($accounts as $account) {
            if (!($account['is_subtotal'] ?? false)) {
                $amount = (float) ($account['current_year_amount'] ?? 0);

                switch ($account['account_category'] ?? '') {
                    case 'asset':
                        $summary['total_assets'] += $amount;
                        break;
                    case 'liability':
                        $summary['total_liabilities'] += $amount;
                        break;
                    case 'equity':
                        $summary['total_equity'] += $amount;
                        break;
                    case 'revenue':
                        $summary['total_revenue'] += $amount;
                        break;
                    case 'expense':
                        $summary['total_expenses'] += $amount;
                        break;
                }
            }
        }

        return $summary;
    }

    /**
     * Calculate activity summary.
     */
    private function calculateActivitySummary(array $activities): array
    {
        $summary = [
            'operating_cash_flow' => 0,
            'investing_cash_flow' => 0,
            'financing_cash_flow' => 0,
            'net_cash_flow' => 0,
            'activity_count' => count($activities)
        ];

        foreach ($activities as $activity) {
            if (!($activity['is_subtotal'] ?? false)) {
                $amount = (float) ($activity['current_year_amount'] ?? 0);

                switch ($activity['activity_category'] ?? '') {
                    case 'operating':
                        $summary['operating_cash_flow'] += $amount;
                        break;
                    case 'investing':
                        $summary['investing_cash_flow'] += $amount;
                        break;
                    case 'financing':
                        $summary['financing_cash_flow'] += $amount;
                        break;
                }
            }
        }

        $summary['net_cash_flow'] = $summary['operating_cash_flow'] +
            $summary['investing_cash_flow'] +
            $summary['financing_cash_flow'];

        return $summary;
    }

    /**
     * Calculate equity summary.
     */
    private function calculateEquitySummary(array $equityChanges): array
    {
        $summary = [
            'total_beginning_balance' => 0,
            'total_additions' => 0,
            'total_reductions' => 0,
            'total_ending_balance' => 0,
            'component_count' => count($equityChanges)
        ];

        foreach ($equityChanges as $change) {
            $summary['total_beginning_balance'] += (float) ($change['beginning_balance'] ?? 0);
            $summary['total_additions'] += (float) ($change['additions'] ?? 0);
            $summary['total_reductions'] += (float) ($change['reductions'] ?? 0);
            $summary['total_ending_balance'] += (float) ($change['ending_balance'] ?? 0);
        }

        return $summary;
    }

    /**
     * Consolidate report data from multiple reports.
     */
    private function consolidateReportData($reports, string $reportType): array
    {
        $consolidated = [];

        switch ($reportType) {
            case 'balance_sheet':
                $consolidated = $this->consolidateBalanceSheetData($reports);
                break;
            case 'income_statement':
                $consolidated = $this->consolidateIncomeStatementData($reports);
                break;
                // Add other report types as needed
        }

        return $consolidated;
    }

    /**
     * Consolidate balance sheet data.
     */
    private function consolidateBalanceSheetData($reports): array
    {
        $consolidated = [
            'total_assets' => 0,
            'total_liabilities' => 0,
            'total_equity' => 0,
            'accounts' => []
        ];

        foreach ($reports as $report) {
            foreach ($report->balanceSheetAccounts as $account) {
                $accountCode = $account->account_code;

                if (!isset($consolidated['accounts'][$accountCode])) {
                    $consolidated['accounts'][$accountCode] = [
                        'account_name' => $account->account_name,
                        'account_category' => $account->account_category,
                        'total_amount' => 0,
                        'cooperative_amounts' => []
                    ];
                }

                $consolidated['accounts'][$accountCode]['total_amount'] += $account->current_year_amount;
                $consolidated['accounts'][$accountCode]['cooperative_amounts'][$report->cooperative->name] = $account->current_year_amount;

                switch ($account->account_category) {
                    case 'asset':
                        $consolidated['total_assets'] += $account->current_year_amount;
                        break;
                    case 'liability':
                        $consolidated['total_liabilities'] += $account->current_year_amount;
                        break;
                    case 'equity':
                        $consolidated['total_equity'] += $account->current_year_amount;
                        break;
                }
            }
        }

        return $consolidated;
    }

    /**
     * Consolidate income statement data.
     */
    private function consolidateIncomeStatementData($reports): array
    {
        $consolidated = [
            'total_revenue' => 0,
            'total_expenses' => 0,
            'net_income' => 0,
            'accounts' => []
        ];

        foreach ($reports as $report) {
            foreach ($report->incomeStatementAccounts as $account) {
                $accountCode = $account->account_code;

                if (!isset($consolidated['accounts'][$accountCode])) {
                    $consolidated['accounts'][$accountCode] = [
                        'account_name' => $account->account_name,
                        'account_category' => $account->account_category,
                        'total_amount' => 0,
                        'cooperative_amounts' => []
                    ];
                }

                $consolidated['accounts'][$accountCode]['total_amount'] += $account->current_year_amount;
                $consolidated['accounts'][$accountCode]['cooperative_amounts'][$report->cooperative->name] = $account->current_year_amount;

                switch ($account->account_category) {
                    case 'revenue':
                        $consolidated['total_revenue'] += $account->current_year_amount;
                        break;
                    case 'expense':
                        $consolidated['total_expenses'] += $account->current_year_amount;
                        break;
                }
            }
        }

        $consolidated['net_income'] = $consolidated['total_revenue'] - $consolidated['total_expenses'];

        return $consolidated;
    }

    /**
     * Generate consolidation summary.
     */
    private function generateConsolidationSummary($reports, string $reportType): array
    {
        return [
            'report_count' => $reports->count(),
            'cooperatives' => $reports->pluck('cooperative.name')->toArray(),
            'total_cooperatives' => $reports->pluck('cooperative_id')->unique()->count(),
            'report_type' => $reportType,
            'generated_at' => now()->toISOString(),
            'generated_by' => auth()->user()->name
        ];
    }

    /**
     * Get relationships to load based on report type.
     */
    private function getRelationshipsForReportType(string $reportType): string
    {
        return match ($reportType) {
            'balance_sheet' => 'balanceSheetAccounts',
            'income_statement' => 'incomeStatementAccounts',
            'equity_changes' => 'equityChanges',
            'cash_flow' => 'cashFlowActivities',
            'member_savings' => 'memberSavings',
            'member_receivables' => 'memberReceivables',
            'npl_receivables' => 'nonPerformingReceivables',
            'shu_distribution' => 'shuDistributions',
            'budget_plan' => 'budgetPlans',
            default => 'cooperative'
        };
    }
}
