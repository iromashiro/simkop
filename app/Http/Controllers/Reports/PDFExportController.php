<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialReport;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class PDFExportController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_koperasi|admin_dinas');
        $this->middleware('throttle:pdf-exports')->only(['export', 'download']);
    }

    /**
     * Export single financial report to PDF
     */
    public function export(Request $request, FinancialReport $report): Response
    {
        try {
            // Check access permissions
            if (!$this->canAccessReport($report)) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            $request->validate([
                'template' => 'sometimes|string|in:standard,detailed,summary',
                'include_notes' => 'sometimes|boolean',
                'include_comparison' => 'sometimes|boolean',
            ]);

            $template = $request->input('template', 'standard');
            $includeNotes = $request->boolean('include_notes', true);
            $includeComparison = $request->boolean('include_comparison', false);

            // Load report with relationships
            $report->load([
                'cooperative:id,name,code,address,phone,email',
                'balanceSheetAccounts',
                'incomeStatementAccounts',
                'equityChanges',
                'cashFlowActivities',
                'memberSavings',
                'memberReceivables',
                'nonPerformingReceivables',
                'shuDistributions',
                'budgetPlans'
            ]);

            // Get comparison data if requested
            $comparisonData = null;
            if ($includeComparison) {
                $comparisonData = $this->getComparisonData($report);
            }

            // Generate PDF based on report type
            $pdf = $this->generatePDF($report, $template, $includeNotes, $comparisonData);

            // Log the export activity
            $this->auditLogService->log(
                'pdf_export',
                'Laporan diekspor ke PDF',
                [
                    'report_id' => $report->id,
                    'report_type' => $report->report_type,
                    'cooperative_id' => $report->cooperative_id,
                    'template' => $template,
                    'include_notes' => $includeNotes,
                    'include_comparison' => $includeComparison
                ]
            );

            $filename = $this->generateFilename($report, $template);

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Error exporting PDF', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal mengekspor laporan ke PDF: ' . $e->getMessage());
        }
    }

    /**
     * Export multiple reports to combined PDF
     */
    public function exportMultiple(Request $request): Response
    {
        try {
            $request->validate([
                'report_ids' => 'required|array|min:1|max:10',
                'report_ids.*' => 'exists:financial_reports,id',
                'template' => 'sometimes|string|in:standard,detailed,summary',
                'include_notes' => 'sometimes|boolean',
            ]);

            $reportIds = $request->input('report_ids');
            $template = $request->input('template', 'standard');
            $includeNotes = $request->boolean('include_notes', true);

            // Load reports with access check
            $reports = FinancialReport::whereIn('id', $reportIds)
                ->with([
                    'cooperative:id,name,code,address',
                    'balanceSheetAccounts',
                    'incomeStatementAccounts',
                    'equityChanges',
                    'cashFlowActivities',
                    'memberSavings',
                    'memberReceivables',
                    'nonPerformingReceivables',
                    'shuDistributions',
                    'budgetPlans'
                ])
                ->get();

            // Filter reports user can access
            $accessibleReports = $reports->filter(function ($report) {
                return $this->canAccessReport($report);
            });

            if ($accessibleReports->isEmpty()) {
                return redirect()->back()
                    ->with('error', 'Tidak ada laporan yang dapat diakses.');
            }

            // Generate combined PDF
            $pdf = $this->generateCombinedPDF($accessibleReports, $template, $includeNotes);

            // Log the export activity
            $this->auditLogService->log(
                'pdf_export_multiple',
                'Multiple laporan diekspor ke PDF',
                [
                    'report_ids' => $accessibleReports->pluck('id')->toArray(),
                    'report_count' => $accessibleReports->count(),
                    'template' => $template,
                    'include_notes' => $includeNotes
                ]
            );

            $filename = 'laporan_keuangan_' . now()->format('Y-m-d_H-i-s') . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Error exporting multiple PDFs', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal mengekspor laporan ke PDF: ' . $e->getMessage());
        }
    }

    /**
     * Preview PDF before download
     */
    public function preview(Request $request, FinancialReport $report): Response
    {
        try {
            if (!$this->canAccessReport($report)) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            $template = $request->input('template', 'standard');
            $includeNotes = $request->boolean('include_notes', true);

            $report->load([
                'cooperative:id,name,code,address,phone,email',
                'balanceSheetAccounts',
                'incomeStatementAccounts'
            ]);

            $pdf = $this->generatePDF($report, $template, $includeNotes);

            return $pdf->stream();
        } catch (\Exception $e) {
            Log::error('Error previewing PDF', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal menampilkan preview PDF.');
        }
    }

    /**
     * Generate PDF for single report
     */
    private function generatePDF(
        FinancialReport $report,
        string $template,
        bool $includeNotes,
        ?array $comparisonData = null
    ): \Barryvdh\DomPDF\PDF {
        $viewData = [
            'report' => $report,
            'cooperative' => $report->cooperative,
            'template' => $template,
            'include_notes' => $includeNotes,
            'comparison_data' => $comparisonData,
            'generated_at' => now(),
            'generated_by' => auth()->user()->name
        ];

        // Select appropriate view based on report type
        $viewName = match ($report->report_type) {
            'balance_sheet' => 'reports.pdf.balance_sheet',
            'income_statement' => 'reports.pdf.income_statement',
            'equity_changes' => 'reports.pdf.equity_changes',
            'cash_flow' => 'reports.pdf.cash_flow',
            'member_savings' => 'reports.pdf.member_savings',
            'member_receivables' => 'reports.pdf.member_receivables',
            'npl_receivables' => 'reports.pdf.npl_receivables',
            'shu_distribution' => 'reports.pdf.shu_distribution',
            'budget_plan' => 'reports.pdf.budget_plan',
            'notes_to_financial' => 'reports.pdf.notes_to_financial',
            default => 'reports.pdf.generic'
        };

        return PDF::loadView($viewName, $viewData)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => false
            ]);
    }

    /**
     * Generate combined PDF for multiple reports
     */
    private function generateCombinedPDF(
        $reports,
        string $template,
        bool $includeNotes
    ): \Barryvdh\DomPDF\PDF {
        $viewData = [
            'reports' => $reports,
            'template' => $template,
            'include_notes' => $includeNotes,
            'generated_at' => now(),
            'generated_by' => auth()->user()->name
        ];

        return PDF::loadView('reports.pdf.combined', $viewData)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => false
            ]);
    }

    /**
     * Get comparison data for previous year
     */
    private function getComparisonData(FinancialReport $report): ?array
    {
        $previousYear = $report->reporting_year - 1;

        $previousReport = FinancialReport::where('cooperative_id', $report->cooperative_id)
            ->where('report_type', $report->report_type)
            ->where('reporting_year', $previousYear)
            ->where('status', 'approved')
            ->with([
                'balanceSheetAccounts',
                'incomeStatementAccounts',
                'equityChanges',
                'cashFlowActivities'
            ])
            ->first();

        if (!$previousReport) {
            return null;
        }

        return [
            'previous_year' => $previousYear,
            'previous_report' => $previousReport,
            'variance_analysis' => $this->calculateVarianceAnalysis($report, $previousReport)
        ];
    }

    /**
     * Calculate variance analysis between current and previous year
     */
    private function calculateVarianceAnalysis(FinancialReport $current, FinancialReport $previous): array
    {
        // This is a simplified variance analysis
        // In production, you'd want more sophisticated calculations
        return [
            'total_assets_variance' => 0, // Calculate based on balance sheet
            'total_revenue_variance' => 0, // Calculate based on income statement
            'net_income_variance' => 0,   // Calculate based on income statement
            'variance_percentage' => 0    // Overall variance percentage
        ];
    }

    /**
     * Generate filename for PDF export
     */
    private function generateFilename(FinancialReport $report, string $template): string
    {
        $reportTypeMap = [
            'balance_sheet' => 'Neraca',
            'income_statement' => 'Laba_Rugi',
            'equity_changes' => 'Perubahan_Ekuitas',
            'cash_flow' => 'Arus_Kas',
            'member_savings' => 'Simpanan_Anggota',
            'member_receivables' => 'Piutang_Anggota',
            'npl_receivables' => 'Piutang_NPL',
            'shu_distribution' => 'Distribusi_SHU',
            'budget_plan' => 'Rencana_Anggaran',
            'notes_to_financial' => 'Catatan_Laporan'
        ];

        $reportTypeName = $reportTypeMap[$report->report_type] ?? 'Laporan';
        $cooperativeName = str_replace(' ', '_', $report->cooperative->name);

        return "{$reportTypeName}_{$cooperativeName}_{$report->reporting_year}_{$template}_" .
            now()->format('Y-m-d_H-i-s') . '.pdf';
    }

    /**
     * Check if current user can access the report
     */
    private function canAccessReport(FinancialReport $report): bool
    {
        $user = auth()->user();

        // Admin Dinas can access all reports
        if ($user->hasRole('admin_dinas')) {
            return true;
        }

        // Admin Koperasi can only access their own cooperative's reports
        if ($user->hasRole('admin_koperasi')) {
            return $user->cooperative_id === $report->cooperative_id;
        }

        return false;
    }
}
