<?php

namespace App\Services\Export;

use App\Models\Financial\FinancialReport;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PDFExportService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Export financial report to PDF.
     */
    public function exportReport(FinancialReport $report, array $options = []): array
    {
        try {
            // Load necessary relationships
            $report->load($this->getRelationshipsForReportType($report->report_type));
            $report->load('cooperative');

            // Prepare data for PDF
            $data = $this->prepareReportData($report, $options);

            // Generate PDF
            $pdf = $this->generatePDF($report->report_type, $data, $options);

            // Save PDF file
            $filename = $this->generateFilename($report, $options);
            $filepath = $this->savePDF($pdf, $filename);

            // Log the export
            $this->auditLogService->log(
                'report_exported_pdf',
                "Financial report exported to PDF: {$filename}",
                [
                    'report_id' => $report->id,
                    'report_type' => $report->report_type,
                    'filename' => $filename,
                    'file_size' => Storage::size($filepath)
                ]
            );

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'download_url' => Storage::url($filepath),
                'file_size' => Storage::size($filepath)
            ];
        } catch (\Exception $e) {
            Log::error('Error exporting report to PDF', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export multiple reports to a single PDF.
     */
    public function exportMultipleReports(array $reportIds, array $options = []): array
    {
        try {
            $reports = FinancialReport::whereIn('id', $reportIds)
                ->with(['cooperative'])
                ->get();

            if ($reports->isEmpty()) {
                throw new \Exception('No reports found for export.');
            }

            // Load relationships for each report
            foreach ($reports as $report) {
                $report->load($this->getRelationshipsForReportType($report->report_type));
            }

            // Prepare combined data
            $combinedData = $this->prepareCombinedReportData($reports, $options);

            // Generate combined PDF
            $pdf = $this->generateCombinedPDF($combinedData, $options);

            // Save PDF file
            $filename = $this->generateCombinedFilename($reports, $options);
            $filepath = $this->savePDF($pdf, $filename);

            // Log the export
            $this->auditLogService->log(
                'multiple_reports_exported_pdf',
                "Multiple financial reports exported to PDF: {$filename}",
                [
                    'report_ids' => $reportIds,
                    'report_count' => count($reports),
                    'filename' => $filename,
                    'file_size' => Storage::size($filepath)
                ]
            );

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'download_url' => Storage::url($filepath),
                'file_size' => Storage::size($filepath),
                'report_count' => count($reports)
            ];
        } catch (\Exception $e) {
            Log::error('Error exporting multiple reports to PDF', [
                'report_ids' => $reportIds,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate PDF with custom template.
     */
    public function generateCustomPDF(array $data, string $template, array $options = []): string
    {
        try {
            $pdf = Pdf::loadView("exports.pdf.{$template}", $data);

            // Apply PDF options
            $this->applyPDFOptions($pdf, $options);

            return $pdf->output();
        } catch (\Exception $e) {
            Log::error('Error generating custom PDF', [
                'template' => $template,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Prepare report data for PDF generation.
     */
    private function prepareReportData(FinancialReport $report, array $options): array
    {
        $data = [
            'report' => $report,
            'cooperative' => $report->cooperative,
            'generated_at' => now(),
            'generated_by' => auth()->user()->name ?? 'System',
            'options' => $options
        ];

        // Add report-specific data
        switch ($report->report_type) {
            case 'balance_sheet':
                $data['accounts'] = $this->prepareBalanceSheetData($report);
                $data['totals'] = $this->calculateBalanceSheetTotals($report);
                break;

            case 'income_statement':
                $data['accounts'] = $this->prepareIncomeStatementData($report);
                $data['totals'] = $this->calculateIncomeStatementTotals($report);
                break;

            case 'cash_flow':
                $data['activities'] = $this->prepareCashFlowData($report);
                $data['totals'] = $this->calculateCashFlowTotals($report);
                break;

            case 'equity_changes':
                $data['equity_changes'] = $report->equityChanges;
                $data['totals'] = $this->calculateEquityChangesTotals($report);
                break;

            case 'member_savings':
                $data['member_savings'] = $report->memberSavings;
                $data['summary'] = $this->calculateMemberSavingsSummary($report);
                break;

            case 'member_receivables':
                $data['member_receivables'] = $report->memberReceivables;
                $data['summary'] = $this->calculateMemberReceivablesSummary($report);
                break;

            case 'npl_receivables':
                $data['npl_receivables'] = $report->nonPerformingReceivables;
                $data['summary'] = $this->calculateNPLReceivablesSummary($report);
                break;

            case 'shu_distribution':
                $data['shu_distributions'] = $report->shuDistributions;
                $data['summary'] = $this->calculateSHUDistributionSummary($report);
                break;

            case 'budget_plan':
                $data['budget_plans'] = $this->prepareBudgetPlanData($report);
                $data['summary'] = $this->calculateBudgetPlanSummary($report);
                break;
        }

        // Add comparison data if requested
        if ($options['include_comparison'] ?? false) {
            $data['comparison'] = $this->getComparisonData($report, $options);
        }

        // Add charts data if requested
        if ($options['include_charts'] ?? false) {
            $data['charts'] = $this->generateChartsData($report, $options);
        }

        return $data;
    }

    /**
     * Generate PDF based on report type.
     */
    private function generatePDF(string $reportType, array $data, array $options): \Barryvdh\DomPDF\PDF
    {
        $template = "exports.pdf.{$reportType}";

        $pdf = Pdf::loadView($template, $data);

        // Apply PDF options
        $this->applyPDFOptions($pdf, $options);

        return $pdf;
    }

    /**
     * Apply PDF options.
     */
    private function applyPDFOptions(\Barryvdh\DomPDF\PDF $pdf, array $options): void
    {
        // Set paper size
        $paperSize = $options['paper_size'] ?? 'a4';
        $orientation = $options['orientation'] ?? 'portrait';
        $pdf->setPaper($paperSize, $orientation);

        // Set DomPDF options
        $pdf->setOptions([
            'defaultFont' => $options['font'] ?? 'Arial',
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isFontSubsettingEnabled' => true,
            'defaultPaperSize' => $paperSize,
            'defaultPaperOrientation' => $orientation
        ]);
    }

    /**
     * Save PDF to storage.
     */
    private function savePDF(\Barryvdh\DomPDF\PDF $pdf, string $filename): string
    {
        $directory = 'exports/pdf/' . date('Y/m');
        $filepath = $directory . '/' . $filename;

        // Ensure directory exists
        Storage::makeDirectory($directory);

        // Save PDF
        Storage::put($filepath, $pdf->output());

        return $filepath;
    }

    /**
     * Generate filename for single report.
     */
    private function generateFilename(FinancialReport $report, array $options): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $reportType = str_replace('_', '-', $report->report_type);
        $cooperativeName = str_replace(' ', '-', $report->cooperative->name);

        return "{$cooperativeName}_{$reportType}_{$report->reporting_year}_{$timestamp}.pdf";
    }

    /**
     * Generate filename for combined reports.
     */
    private function generateCombinedFilename($reports, array $options): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $cooperativeName = str_replace(' ', '-', $reports->first()->cooperative->name);
        $year = $reports->first()->reporting_year;

        return "{$cooperativeName}_combined_reports_{$year}_{$timestamp}.pdf";
    }

    /**
     * Prepare combined report data.
     */
    private function prepareCombinedReportData($reports, array $options): array
    {
        $combinedData = [
            'reports' => [],
            'cooperative' => $reports->first()->cooperative,
            'reporting_year' => $reports->first()->reporting_year,
            'generated_at' => now(),
            'generated_by' => auth()->user()->name ?? 'System',
            'options' => $options
        ];

        foreach ($reports as $report) {
            $reportData = $this->prepareReportData($report, $options);
            $combinedData['reports'][$report->report_type] = $reportData;
        }

        return $combinedData;
    }

    /**
     * Generate combined PDF.
     */
    private function generateCombinedPDF(array $data, array $options): \Barryvdh\DomPDF\PDF
    {
        $template = 'exports.pdf.combined_reports';

        $pdf = Pdf::loadView($template, $data);

        // Apply PDF options
        $this->applyPDFOptions($pdf, $options);

        return $pdf;
    }

    /**
     * Prepare balance sheet data for PDF.
     */
    private function prepareBalanceSheetData(FinancialReport $report): array
    {
        $accounts = $report->balanceSheetAccounts->sortBy('sort_order');

        return [
            'assets' => $accounts->where('account_category', 'asset'),
            'liabilities' => $accounts->where('account_category', 'liability'),
            'equity' => $accounts->where('account_category', 'equity')
        ];
    }

    /**
     * Calculate balance sheet totals.
     */
    private function calculateBalanceSheetTotals(FinancialReport $report): array
    {
        $accounts = $report->balanceSheetAccounts;

        return [
            'total_assets' => $accounts->where('account_category', 'asset')->sum('current_year_amount'),
            'total_liabilities' => $accounts->where('account_category', 'liability')->sum('current_year_amount'),
            'total_equity' => $accounts->where('account_category', 'equity')->sum('current_year_amount')
        ];
    }

    /**
     * Prepare income statement data for PDF.
     */
    private function prepareIncomeStatementData(FinancialReport $report): array
    {
        $accounts = $report->incomeStatementAccounts->sortBy('sort_order');

        return [
            'revenue' => $accounts->where('account_category', 'revenue'),
            'expenses' => $accounts->where('account_category', 'expense'),
            'other_income' => $accounts->where('account_category', 'other_income'),
            'other_expenses' => $accounts->where('account_category', 'other_expense')
        ];
    }

    /**
     * Calculate income statement totals.
     */
    private function calculateIncomeStatementTotals(FinancialReport $report): array
    {
        $accounts = $report->incomeStatementAccounts;

        $totalRevenue = $accounts->where('account_category', 'revenue')->sum('current_year_amount');
        $totalExpenses = $accounts->where('account_category', 'expense')->sum('current_year_amount');
        $otherIncome = $accounts->where('account_category', 'other_income')->sum('current_year_amount');
        $otherExpenses = $accounts->where('account_category', 'other_expense')->sum('current_year_amount');

        return [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'other_income' => $otherIncome,
            'other_expenses' => $otherExpenses,
            'net_income' => $totalRevenue - $totalExpenses + $otherIncome - $otherExpenses
        ];
    }

    /**
     * Prepare cash flow data for PDF.
     */
    private function prepareCashFlowData(FinancialReport $report): array
    {
        $activities = $report->cashFlowActivities->sortBy('sort_order');

        return [
            'operating' => $activities->where('activity_category', 'operating'),
            'investing' => $activities->where('activity_category', 'investing'),
            'financing' => $activities->where('activity_category', 'financing')
        ];
    }

    /**
     * Calculate cash flow totals.
     */
    private function calculateCashFlowTotals(FinancialReport $report): array
    {
        $activities = $report->cashFlowActivities;

        return [
            'operating_total' => $activities->where('activity_category', 'operating')->sum('current_year_amount'),
            'investing_total' => $activities->where('activity_category', 'investing')->sum('current_year_amount'),
            'financing_total' => $activities->where('activity_category', 'financing')->sum('current_year_amount'),
            'net_cash_flow' => $activities->sum('current_year_amount'),
            'beginning_cash' => $report->data['beginning_cash_balance'] ?? 0,
            'ending_cash' => $report->data['ending_cash_balance'] ?? 0
        ];
    }

    /**
     * Calculate equity changes totals.
     */
    private function calculateEquityChangesTotals(FinancialReport $report): array
    {
        $equityChanges = $report->equityChanges;

        return [
            'total_beginning_balance' => $equityChanges->sum('beginning_balance'),
            'total_additions' => $equityChanges->sum('additions'),
            'total_reductions' => $equityChanges->sum('reductions'),
            'total_ending_balance' => $equityChanges->sum('ending_balance'),
            'net_change' => $equityChanges->sum('ending_balance') - $equityChanges->sum('beginning_balance')
        ];
    }

    /**
     * Calculate member savings summary.
     */
    private function calculateMemberSavingsSummary(FinancialReport $report): array
    {
        $memberSavings = $report->memberSavings;

        return [
            'total_members' => $memberSavings->count(),
            'total_beginning_balance' => $memberSavings->sum('beginning_balance'),
            'total_deposits' => $memberSavings->sum('deposits'),
            'total_withdrawals' => $memberSavings->sum('withdrawals'),
            'total_ending_balance' => $memberSavings->sum('ending_balance'),
            'total_interest_earned' => $memberSavings->sum('interest_earned'),
            'by_savings_type' => $memberSavings->groupBy('savings_type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_balance' => $group->sum('ending_balance')
                ];
            })
        ];
    }

    /**
     * Calculate member receivables summary.
     */
    private function calculateMemberReceivablesSummary(FinancialReport $report): array
    {
        $memberReceivables = $report->memberReceivables;

        return [
            'total_loans' => $memberReceivables->count(),
            'total_loan_amount' => $memberReceivables->sum('loan_amount'),
            'total_outstanding_balance' => $memberReceivables->sum('outstanding_balance'),
            'average_interest_rate' => $memberReceivables->avg('interest_rate'),
            'by_loan_type' => $memberReceivables->groupBy('loan_type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('loan_amount'),
                    'total_outstanding' => $group->sum('outstanding_balance')
                ];
            }),
            'by_payment_status' => $memberReceivables->groupBy('payment_status')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_outstanding' => $group->sum('outstanding_balance')
                ];
            })
        ];
    }

    /**
     * Calculate NPL receivables summary.
     */
    private function calculateNPLReceivablesSummary(FinancialReport $report): array
    {
        $nplReceivables = $report->nonPerformingReceivables;

        return [
            'total_npl_loans' => $nplReceivables->count(),
            'total_original_amount' => $nplReceivables->sum('original_loan_amount'),
            'total_outstanding_balance' => $nplReceivables->sum('outstanding_balance'),
            'total_provision_amount' => $nplReceivables->sum('provision_amount'),
            'average_days_past_due' => $nplReceivables->avg('days_past_due'),
            'by_classification' => $nplReceivables->groupBy('npl_classification')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_outstanding' => $group->sum('outstanding_balance'),
                    'total_provision' => $group->sum('provision_amount')
                ];
            })
        ];
    }

    /**
     * Calculate SHU distribution summary.
     */
    private function calculateSHUDistributionSummary(FinancialReport $report): array
    {
        $shuDistributions = $report->shuDistributions;

        return [
            'total_members' => $shuDistributions->count(),
            'total_shu_distributed' => $shuDistributions->sum('total_shu_received'),
            'total_tax_deduction' => $shuDistributions->sum('tax_deduction'),
            'total_net_shu' => $shuDistributions->sum('net_shu_received'),
            'average_shu_per_member' => $shuDistributions->avg('total_shu_received'),
            'by_member_type' => $shuDistributions->groupBy('member_type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_shu' => $group->sum('total_shu_received')
                ];
            }),
            'by_payment_status' => $shuDistributions->groupBy('payment_status')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('net_shu_received')
                ];
            })
        ];
    }

    /**
     * Prepare budget plan data for PDF.
     */
    private function prepareBudgetPlanData(FinancialReport $report): array
    {
        $budgetPlans = $report->budgetPlans->sortBy('sort_order');

        return [
            'revenue' => $budgetPlans->where('budget_category', 'revenue'),
            'expense' => $budgetPlans->where('budget_category', 'expense'),
            'investment' => $budgetPlans->where('budget_category', 'investment'),
            'financing' => $budgetPlans->where('budget_category', 'financing')
        ];
    }

    /**
     * Calculate budget plan summary.
     */
    private function calculateBudgetPlanSummary(FinancialReport $report): array
    {
        $budgetPlans = $report->budgetPlans;

        return [
            'total_revenue_budget' => $budgetPlans->where('budget_category', 'revenue')->sum('planned_amount'),
            'total_expense_budget' => $budgetPlans->where('budget_category', 'expense')->sum('planned_amount'),
            'total_investment_budget' => $budgetPlans->where('budget_category', 'investment')->sum('planned_amount'),
            'total_financing_budget' => $budgetPlans->where('budget_category', 'financing')->sum('planned_amount'),
            'net_budget' => $budgetPlans->where('budget_category', 'revenue')->sum('planned_amount') -
                $budgetPlans->where('budget_category', 'expense')->sum('planned_amount'),
            'by_priority' => $budgetPlans->groupBy('priority_level')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('planned_amount')
                ];
            })
        ];
    }

    /**
     * Get comparison data for previous years.
     */
    private function getComparisonData(FinancialReport $report, array $options): array
    {
        $comparisonYears = $options['comparison_years'] ?? 2;
        $previousYears = range($report->reporting_year - $comparisonYears, $report->reporting_year - 1);

        $comparisonData = [];

        foreach ($previousYears as $year) {
            $previousReport = FinancialReport::where('cooperative_id', $report->cooperative_id)
                ->where('report_type', $report->report_type)
                ->where('reporting_year', $year)
                ->where('status', 'approved')
                ->first();

            if ($previousReport) {
                $comparisonData[$year] = $this->extractComparisonMetrics($previousReport);
            }
        }

        return $comparisonData;
    }

    /**
     * Extract comparison metrics from report.
     */
    private function extractComparisonMetrics(FinancialReport $report): array
    {
        switch ($report->report_type) {
            case 'balance_sheet':
                return [
                    'total_assets' => $report->balanceSheetAccounts->where('account_category', 'asset')->sum('current_year_amount'),
                    'total_liabilities' => $report->balanceSheetAccounts->where('account_category', 'liability')->sum('current_year_amount'),
                    'total_equity' => $report->balanceSheetAccounts->where('account_category', 'equity')->sum('current_year_amount')
                ];

            case 'income_statement':
                $totalRevenue = $report->incomeStatementAccounts->where('account_category', 'revenue')->sum('current_year_amount');
                $totalExpenses = $report->incomeStatementAccounts->where('account_category', 'expense')->sum('current_year_amount');
                return [
                    'total_revenue' => $totalRevenue,
                    'total_expenses' => $totalExpenses,
                    'net_income' => $totalRevenue - $totalExpenses
                ];

            default:
                return [];
        }
    }

    /**
     * Generate charts data for PDF.
     */
    private function generateChartsData(FinancialReport $report, array $options): array
    {
        // This would generate chart data that can be rendered in PDF
        // For now, return placeholder
        return [
            'charts_enabled' => true,
            'chart_types' => ['pie', 'bar', 'line']
        ];
    }

    /**
     * Get relationships for report type.
     */
    private function getRelationshipsForReportType(string $reportType): string
    {
        return match ($reportType) {
            'balance_sheet' => 'balanceSheetAccounts',
            'income_statement' => 'incomeStatementAccounts',
            'cash_flow' => 'cashFlowActivities',
            'equity_changes' => 'equityChanges',
            'member_savings' => 'memberSavings',
            'member_receivables' => 'memberReceivables',
            'npl_receivables' => 'nonPerformingReceivables',
            'shu_distribution' => 'shuDistributions',
            'budget_plan' => 'budgetPlans',
            default => 'cooperative'
        };
    }
}
