<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Financial\FinancialReport;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExcelExportController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_koperasi|admin_dinas');
        $this->middleware('throttle:excel-exports')->only(['export', 'download']);
    }

    /**
     * Export single financial report to Excel
     */
    public function export(Request $request, FinancialReport $report): Response
    {
        try {
            if (!$this->canAccessReport($report)) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            $request->validate([
                'format' => 'sometimes|string|in:xlsx,csv',
                'include_formulas' => 'sometimes|boolean',
                'include_charts' => 'sometimes|boolean',
                'include_comparison' => 'sometimes|boolean',
            ]);

            $format = $request->input('format', 'xlsx');
            $includeFormulas = $request->boolean('include_formulas', true);
            $includeCharts = $request->boolean('include_charts', false);
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

            // Generate Excel file
            $spreadsheet = $this->generateExcel($report, $includeFormulas, $includeCharts, $comparisonData);

            // Log the export activity
            $this->auditLogService->log(
                'excel_export',
                'Laporan diekspor ke Excel',
                [
                    'report_id' => $report->id,
                    'report_type' => $report->report_type,
                    'cooperative_id' => $report->cooperative_id,
                    'format' => $format,
                    'include_formulas' => $includeFormulas,
                    'include_charts' => $includeCharts,
                    'include_comparison' => $includeComparison
                ]
            );

            $filename = $this->generateFilename($report, $format);

            // Create response
            $writer = new Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
                'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
                'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
                'Pragma' => 'public'
            ]);
        } catch (\Exception $e) {
            Log::error('Error exporting Excel', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal mengekspor laporan ke Excel: ' . $e->getMessage());
        }
    }

    /**
     * Export multiple reports to Excel workbook
     */
    public function exportMultiple(Request $request): Response
    {
        try {
            $request->validate([
                'report_ids' => 'required|array|min:1|max:10',
                'report_ids.*' => 'exists:financial_reports,id',
                'format' => 'sometimes|string|in:xlsx,csv',
                'include_formulas' => 'sometimes|boolean',
                'separate_sheets' => 'sometimes|boolean',
            ]);

            $reportIds = $request->input('report_ids');
            $format = $request->input('format', 'xlsx');
            $includeFormulas = $request->boolean('include_formulas', true);
            $separateSheets = $request->boolean('separate_sheets', true);

            // Load reports with access check
            $reports = FinancialReport::whereIn('id', $reportIds)
                ->with([
                    'cooperative:id,name,code',
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

            // Generate Excel workbook
            $spreadsheet = $this->generateMultipleExcel($accessibleReports, $includeFormulas, $separateSheets);

            // Log the export activity
            $this->auditLogService->log(
                'excel_export_multiple',
                'Multiple laporan diekspor ke Excel',
                [
                    'report_ids' => $accessibleReports->pluck('id')->toArray(),
                    'report_count' => $accessibleReports->count(),
                    'format' => $format,
                    'include_formulas' => $includeFormulas,
                    'separate_sheets' => $separateSheets
                ]
            );

            $filename = 'laporan_keuangan_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

            $writer = new Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0'
            ]);
        } catch (\Exception $e) {
            Log::error('Error exporting multiple Excel files', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal mengekspor laporan ke Excel: ' . $e->getMessage());
        }
    }

    /**
     * Export data analysis to Excel
     */
    public function exportAnalysis(Request $request): Response
    {
        try {
            $request->validate([
                'cooperative_ids' => 'sometimes|array',
                'cooperative_ids.*' => 'exists:cooperatives,id',
                'year_from' => 'required|integer|min:2020|max:' . (now()->year + 1),
                'year_to' => 'required|integer|min:2020|max:' . (now()->year + 1),
                'report_types' => 'sometimes|array',
                'report_types.*' => 'string|in:balance_sheet,income_statement,cash_flow',
                'analysis_type' => 'required|string|in:trend,comparison,summary'
            ]);

            $cooperativeIds = $request->input('cooperative_ids', []);
            $yearFrom = $request->integer('year_from');
            $yearTo = $request->integer('year_to');
            $reportTypes = $request->input('report_types', ['balance_sheet', 'income_statement']);
            $analysisType = $request->input('analysis_type');

            // Build query for analysis data
            $query = FinancialReport::with(['cooperative:id,name,code'])
                ->where('status', 'approved')
                ->whereBetween('reporting_year', [$yearFrom, $yearTo])
                ->whereIn('report_type', $reportTypes);

            // Apply cooperative filter for admin_koperasi
            if (auth()->user()->hasRole('admin_koperasi')) {
                $query->where('cooperative_id', auth()->user()->cooperative_id);
            } elseif (!empty($cooperativeIds)) {
                $query->whereIn('cooperative_id', $cooperativeIds);
            }

            $reports = $query->orderBy('cooperative_id')
                ->orderBy('reporting_year')
                ->orderBy('report_type')
                ->get();

            if ($reports->isEmpty()) {
                return redirect()->back()
                    ->with('error', 'Tidak ada data untuk analisis yang diminta.');
            }

            // Generate analysis Excel
            $spreadsheet = $this->generateAnalysisExcel($reports, $analysisType, $yearFrom, $yearTo);

            // Log the export activity
            $this->auditLogService->log(
                'excel_export_analysis',
                'Analisis data diekspor ke Excel',
                [
                    'analysis_type' => $analysisType,
                    'year_from' => $yearFrom,
                    'year_to' => $yearTo,
                    'report_types' => $reportTypes,
                    'cooperative_count' => $reports->pluck('cooperative_id')->unique()->count(),
                    'report_count' => $reports->count()
                ]
            );

            $filename = "analisis_{$analysisType}_{$yearFrom}-{$yearTo}_" . now()->format('Y-m-d_H-i-s') . '.xlsx';

            $writer = new Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0'
            ]);
        } catch (\Exception $e) {
            Log::error('Error exporting analysis Excel', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal mengekspor analisis ke Excel: ' . $e->getMessage());
        }
    }

    /**
     * Generate Excel for single report
     */
    private function generateExcel(
        FinancialReport $report,
        bool $includeFormulas,
        bool $includeCharts,
        ?array $comparisonData = null
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set document properties
        $spreadsheet->getProperties()
            ->setCreator(auth()->user()->name)
            ->setTitle("Laporan Keuangan - {$report->cooperative->name}")
            ->setSubject("Laporan {$report->report_type} Tahun {$report->reporting_year}")
            ->setDescription("Generated by SIMKOP")
            ->setKeywords("laporan keuangan koperasi")
            ->setCategory("Financial Report");

        // Generate content based on report type
        switch ($report->report_type) {
            case 'balance_sheet':
                $this->generateBalanceSheetExcel($sheet, $report, $includeFormulas, $comparisonData);
                break;
            case 'income_statement':
                $this->generateIncomeStatementExcel($sheet, $report, $includeFormulas, $comparisonData);
                break;
            case 'cash_flow':
                $this->generateCashFlowExcel($sheet, $report, $includeFormulas, $comparisonData);
                break;
            default:
                $this->generateGenericExcel($sheet, $report, $includeFormulas);
                break;
        }

        // Add charts if requested
        if ($includeCharts) {
            $this->addChartsToExcel($spreadsheet, $report);
        }

        return $spreadsheet;
    }

    /**
     * Generate Balance Sheet Excel content
     */
    private function generateBalanceSheetExcel($sheet, FinancialReport $report, bool $includeFormulas, ?array $comparisonData = null): void
    {
        $sheet->setTitle('Neraca');

        // Header
        $sheet->setCellValue('A1', 'NERACA');
        $sheet->setCellValue('A2', $report->cooperative->name);
        $sheet->setCellValue('A3', "Per 31 Desember {$report->reporting_year}");
        $sheet->setCellValue('A4', '(Dalam Rupiah)');

        // Style header
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(16);
        $sheet->getStyle('A2:A4')->getFont()->setSize(12);

        $row = 6;

        // Column headers
        $sheet->setCellValue('A' . $row, 'AKUN');
        $sheet->setCellValue('B' . $row, 'CATATAN');
        $sheet->setCellValue('C' . $row, $report->reporting_year);

        if ($comparisonData) {
            $sheet->setCellValue('D' . $row, $comparisonData['previous_year']);
        }

        // Style column headers
        $headerRange = $comparisonData ? 'A' . $row . ':D' . $row : 'A' . $row . ':C' . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');

        $row++;

        // ASSETS
        $sheet->setCellValue('A' . $row, 'ASET');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $assetsStartRow = $row;
        $totalAssets = 0;

        foreach ($report->balanceSheetAccounts->where('account_category', 'asset') as $account) {
            $sheet->setCellValue('A' . $row, $account->account_name);
            $sheet->setCellValue('B' . $row, $account->note_reference);
            $sheet->setCellValue('C' . $row, $account->current_year_amount);

            if ($comparisonData) {
                $previousAmount = $this->getPreviousYearAmount($comparisonData['previous_report'], $account);
                $sheet->setCellValue('D' . $row, $previousAmount);
            }

            $totalAssets += $account->current_year_amount;
            $row++;
        }

        // Total Assets
        $sheet->setCellValue('A' . $row, 'TOTAL ASET');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        if ($includeFormulas) {
            $sheet->setCellValue('C' . $row, "=SUM(C{$assetsStartRow}:C" . ($row - 1) . ")");
        } else {
            $sheet->setCellValue('C' . $row, $totalAssets);
        }

        $row += 2;

        // LIABILITIES
        $sheet->setCellValue('A' . $row, 'KEWAJIBAN');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $liabilitiesStartRow = $row;
        $totalLiabilities = 0;

        foreach ($report->balanceSheetAccounts->where('account_category', 'liability') as $account) {
            $sheet->setCellValue('A' . $row, $account->account_name);
            $sheet->setCellValue('B' . $row, $account->note_reference);
            $sheet->setCellValue('C' . $row, $account->current_year_amount);

            if ($comparisonData) {
                $previousAmount = $this->getPreviousYearAmount($comparisonData['previous_report'], $account);
                $sheet->setCellValue('D' . $row, $previousAmount);
            }

            $totalLiabilities += $account->current_year_amount;
            $row++;
        }

        // Total Liabilities
        $sheet->setCellValue('A' . $row, 'TOTAL KEWAJIBAN');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        if ($includeFormulas) {
            $sheet->setCellValue('C' . $row, "=SUM(C{$liabilitiesStartRow}:C" . ($row - 1) . ")");
        } else {
            $sheet->setCellValue('C' . $row, $totalLiabilities);
        }

        $row += 2;

        // EQUITY
        $sheet->setCellValue('A' . $row, 'EKUITAS');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $equityStartRow = $row;
        $totalEquity = 0;

        foreach ($report->balanceSheetAccounts->where('account_category', 'equity') as $account) {
            $sheet->setCellValue('A' . $row, $account->account_name);
            $sheet->setCellValue('B' . $row, $account->note_reference);
            $sheet->setCellValue('C' . $row, $account->current_year_amount);

            if ($comparisonData) {
                $previousAmount = $this->getPreviousYearAmount($comparisonData['previous_report'], $account);
                $sheet->setCellValue('D' . $row, $previousAmount);
            }

            $totalEquity += $account->current_year_amount;
            $row++;
        }

        // Total Equity
        $sheet->setCellValue('A' . $row, 'TOTAL EKUITAS');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        if ($includeFormulas) {
            $sheet->setCellValue('C' . $row, "=SUM(C{$equityStartRow}:C" . ($row - 1) . ")");
        } else {
            $sheet->setCellValue('C' . $row, $totalEquity);
        }

        $row++;

        // Total Liabilities + Equity
        $sheet->setCellValue('A' . $row, 'TOTAL KEWAJIBAN DAN EKUITAS');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('C' . $row, $totalLiabilities + $totalEquity);

        // Format numbers
        $lastColumn = $comparisonData ? 'D' : 'C';
        $sheet->getStyle("C6:{$lastColumn}{$row}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // Auto-size columns
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Add borders
        $sheet->getStyle("A6:{$lastColumn}{$row}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * Generate Income Statement Excel content
     */
    private function generateIncomeStatementExcel($sheet, FinancialReport $report, bool $includeFormulas, ?array $comparisonData = null): void
    {
        $sheet->setTitle('Laba Rugi');

        // Header
        $sheet->setCellValue('A1', 'LAPORAN LABA RUGI');
        $sheet->setCellValue('A2', $report->cooperative->name);
        $sheet->setCellValue('A3', "Untuk Tahun yang Berakhir 31 Desember {$report->reporting_year}");
        $sheet->setCellValue('A4', '(Dalam Rupiah)');

        // Style header
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(16);
        $sheet->getStyle('A2:A4')->getFont()->setSize(12);

        $row = 6;

        // Column headers
        $sheet->setCellValue('A' . $row, 'AKUN');
        $sheet->setCellValue('B' . $row, 'CATATAN');
        $sheet->setCellValue('C' . $row, $report->reporting_year);

        if ($comparisonData) {
            $sheet->setCellValue('D' . $row, $comparisonData['previous_year']);
        }

        // Style column headers
        $headerRange = $comparisonData ? 'A' . $row . ':D' . $row : 'A' . $row . ':C' . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');

        $row++;

        // REVENUES
        $sheet->setCellValue('A' . $row, 'PENDAPATAN');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $revenuesStartRow = $row;
        $totalRevenues = 0;

        foreach ($report->incomeStatementAccounts->where('account_category', 'revenue') as $account) {
            $sheet->setCellValue('A' . $row, $account->account_name);
            $sheet->setCellValue('B' . $row, $account->note_reference);
            $sheet->setCellValue('C' . $row, $account->current_year_amount);

            if ($comparisonData) {
                $previousAmount = $this->getPreviousYearIncomeAmount($comparisonData['previous_report'], $account);
                $sheet->setCellValue('D' . $row, $previousAmount);
            }

            $totalRevenues += $account->current_year_amount;
            $row++;
        }

        // Total Revenues
        $sheet->setCellValue('A' . $row, 'TOTAL PENDAPATAN');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        if ($includeFormulas) {
            $sheet->setCellValue('C' . $row, "=SUM(C{$revenuesStartRow}:C" . ($row - 1) . ")");
        } else {
            $sheet->setCellValue('C' . $row, $totalRevenues);
        }

        $row += 2;

        // EXPENSES
        $sheet->setCellValue('A' . $row, 'BEBAN');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $expensesStartRow = $row;
        $totalExpenses = 0;

        foreach ($report->incomeStatementAccounts->where('account_category', 'expense') as $account) {
            $sheet->setCellValue('A' . $row, $account->account_name);
            $sheet->setCellValue('B' . $row, $account->note_reference);
            $sheet->setCellValue('C' . $row, $account->current_year_amount);

            if ($comparisonData) {
                $previousAmount = $this->getPreviousYearIncomeAmount($comparisonData['previous_report'], $account);
                $sheet->setCellValue('D' . $row, $previousAmount);
            }

            $totalExpenses += $account->current_year_amount;
            $row++;
        }

        // Total Expenses
        $sheet->setCellValue('A' . $row, 'TOTAL BEBAN');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        if ($includeFormulas) {
            $sheet->setCellValue('C' . $row, "=SUM(C{$expensesStartRow}:C" . ($row - 1) . ")");
        } else {
            $sheet->setCellValue('C' . $row, $totalExpenses);
        }

        $row += 2;

        // Net Income
        $sheet->setCellValue('A' . $row, 'LABA (RUGI) BERSIH');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('C' . $row, $totalRevenues - $totalExpenses);

        // Format numbers
        $lastColumn = $comparisonData ? 'D' : 'C';
        $sheet->getStyle("C6:{$lastColumn}{$row}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // Auto-size columns
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Add borders
        $sheet->getStyle("A6:{$lastColumn}{$row}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * Generate Cash Flow Excel content
     */
    private function generateCashFlowExcel($sheet, FinancialReport $report, bool $includeFormulas, ?array $comparisonData = null): void
    {
        $sheet->setTitle('Arus Kas');

        // Header
        $sheet->setCellValue('A1', 'LAPORAN ARUS KAS');
        $sheet->setCellValue('A2', $report->cooperative->name);
        $sheet->setCellValue('A3', "Untuk Tahun yang Berakhir 31 Desember {$report->reporting_year}");
        $sheet->setCellValue('A4', '(Dalam Rupiah)');

        // Style header
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(16);
        $sheet->getStyle('A2:A4')->getFont()->setSize(12);

        $row = 6;

        // Column headers
        $sheet->setCellValue('A' . $row, 'AKTIVITAS');
        $sheet->setCellValue('B' . $row, 'CATATAN');
        $sheet->setCellValue('C' . $row, $report->reporting_year);

        if ($comparisonData) {
            $sheet->setCellValue('D' . $row, $comparisonData['previous_year']);
        }

        // Style column headers
        $headerRange = $comparisonData ? 'A' . $row . ':D' . $row : 'A' . $row . ':C' . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');

        $row++;

        // Operating Activities
        $sheet->setCellValue('A' . $row, 'ARUS KAS DARI AKTIVITAS OPERASI');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $operatingStartRow = $row;
        $totalOperating = 0;

        foreach ($report->cashFlowActivities->where('activity_category', 'operating') as $activity) {
            $sheet->setCellValue('A' . $row, $activity->activity_description);
            $sheet->setCellValue('B' . $row, $activity->note_reference);
            $sheet->setCellValue('C' . $row, $activity->current_year_amount);

            if ($comparisonData) {
                $previousAmount = $this->getPreviousYearCashFlowAmount($comparisonData['previous_report'], $activity);
                $sheet->setCellValue('D' . $row, $previousAmount);
            }

            $totalOperating += $activity->current_year_amount;
            $row++;
        }

        // Net Operating Cash Flow
        $sheet->setCellValue('A' . $row, 'Arus Kas Bersih dari Aktivitas Operasi');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        if ($includeFormulas) {
            $sheet->setCellValue('C' . $row, "=SUM(C{$operatingStartRow}:C" . ($row - 1) . ")");
        } else {
            $sheet->setCellValue('C' . $row, $totalOperating);
        }

        $row += 2;

        // Investing Activities
        $sheet->setCellValue('A' . $row, 'ARUS KAS DARI AKTIVITAS INVESTASI');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $investingStartRow = $row;
        $totalInvesting = 0;

        foreach ($report->cashFlowActivities->where('activity_category', 'investing') as $activity) {
            $sheet->setCellValue('A' . $row, $activity->activity_description);
            $sheet->setCellValue('B' . $row, $activity->note_reference);
            $sheet->setCellValue('C' . $row, $activity->current_year_amount);

            if ($comparisonData) {
                $previousAmount = $this->getPreviousYearCashFlowAmount($comparisonData['previous_report'], $activity);
                $sheet->setCellValue('D' . $row, $previousAmount);
            }

            $totalInvesting += $activity->current_year_amount;
            $row++;
        }

        // Net Investing Cash Flow
        $sheet->setCellValue('A' . $row, 'Arus Kas Bersih dari Aktivitas Investasi');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        if ($includeFormulas) {
            $sheet->setCellValue('C' . $row, "=SUM(C{$investingStartRow}:C" . ($row - 1) . ")");
        } else {
            $sheet->setCellValue('C' . $row, $totalInvesting);
        }

        $row += 2;

        // Financing Activities
        $sheet->setCellValue('A' . $row, 'ARUS KAS DARI AKTIVITAS PENDANAAN');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $financingStartRow = $row;
        $totalFinancing = 0;

        foreach ($report->cashFlowActivities->where('activity_category', 'financing') as $activity) {
            $sheet->setCellValue('A' . $row, $activity->activity_description);
            $sheet->setCellValue('B' . $row, $activity->note_reference);
            $sheet->setCellValue('C' . $row, $activity->current_year_amount);

            if ($comparisonData) {
                $previousAmount = $this->getPreviousYearCashFlowAmount($comparisonData['previous_report'], $activity);
                $sheet->setCellValue('D' . $row, $previousAmount);
            }

            $totalFinancing += $activity->current_year_amount;
            $row++;
        }

        // Net Financing Cash Flow
        $sheet->setCellValue('A' . $row, 'Arus Kas Bersih dari Aktivitas Pendanaan');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        if ($includeFormulas) {
            $sheet->setCellValue('C' . $row, "=SUM(C{$financingStartRow}:C" . ($row - 1) . ")");
        } else {
            $sheet->setCellValue('C' . $row, $totalFinancing);
        }

        $row += 2;

        // Net Change in Cash
        $sheet->setCellValue('A' . $row, 'KENAIKAN (PENURUNAN) BERSIH KAS');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('C' . $row, $totalOperating + $totalInvesting + $totalFinancing);

        // Format numbers
        $lastColumn = $comparisonData ? 'D' : 'C';
        $sheet->getStyle("C6:{$lastColumn}{$row}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // Auto-size columns
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Add borders
        $sheet->getStyle("A6:{$lastColumn}{$row}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * Generate generic Excel content for other report types
     */
    private function generateGenericExcel($sheet, FinancialReport $report, bool $includeFormulas): void
    {
        $sheet->setTitle(ucfirst(str_replace('_', ' ', $report->report_type)));

        // Header
        $sheet->setCellValue('A1', strtoupper(str_replace('_', ' ', $report->report_type)));
        $sheet->setCellValue('A2', $report->cooperative->name);
        $sheet->setCellValue('A3', "Tahun {$report->reporting_year}");

        // Style header
        $sheet->getStyle('A1:A3')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(16);

        $row = 5;

        // Add report data from JSON
        if ($report->data) {
            $this->addJsonDataToExcel($sheet, $report->data, $row);
        }

        // Auto-size columns
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * Add JSON data to Excel sheet
     */
    private function addJsonDataToExcel($sheet, array $data, int &$row): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sheet->setCellValue('A' . $row, strtoupper(str_replace('_', ' ', $key)));
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;

                $this->addJsonDataToExcel($sheet, $value, $row);
                $row++;
            } else {
                $sheet->setCellValue('A' . $row, str_replace('_', ' ', $key));
                $sheet->setCellValue('B' . $row, $value);
                $row++;
            }
        }
    }

    /**
     * Generate Excel for multiple reports
     */
    private function generateMultipleExcel($reports, bool $includeFormulas, bool $separateSheets): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // Set document properties
        $spreadsheet->getProperties()
            ->setCreator(auth()->user()->name)
            ->setTitle("Laporan Keuangan Multiple")
            ->setSubject("Multiple Financial Reports")
            ->setDescription("Generated by SIMKOP")
            ->setKeywords("laporan keuangan koperasi multiple")
            ->setCategory("Financial Reports");

        if ($separateSheets) {
            // Create separate sheet for each report
            $sheetIndex = 0;
            foreach ($reports as $report) {
                if ($sheetIndex > 0) {
                    $spreadsheet->createSheet();
                }

                $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);
                $sheetName = substr($report->cooperative->name . ' ' . $report->reporting_year, 0, 31);
                $sheet->setTitle($sheetName);

                // Generate content based on report type
                switch ($report->report_type) {
                    case 'balance_sheet':
                        $this->generateBalanceSheetExcel($sheet, $report, $includeFormulas);
                        break;
                    case 'income_statement':
                        $this->generateIncomeStatementExcel($sheet, $report, $includeFormulas);
                        break;
                    case 'cash_flow':
                        $this->generateCashFlowExcel($sheet, $report, $includeFormulas);
                        break;
                    default:
                        $this->generateGenericExcel($sheet, $report, $includeFormulas);
                        break;
                }

                $sheetIndex++;
            }
        } else {
            // Combine all reports in single sheet
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Combined Reports');

            $row = 1;
            foreach ($reports as $index => $report) {
                if ($index > 0) {
                    $row += 3; // Add spacing between reports
                }

                $sheet->setCellValue('A' . $row, $report->cooperative->name . ' - ' . $report->report_type . ' - ' . $report->reporting_year);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
                $row += 2;

                // Add simplified report data
                if ($report->data) {
                    $this->addJsonDataToExcel($sheet, $report->data, $row);
                }
            }

            // Auto-size columns
            foreach (range('A', 'E') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }

        return $spreadsheet;
    }

    /**
     * Generate analysis Excel
     */
    private function generateAnalysisExcel($reports, string $analysisType, int $yearFrom, int $yearTo): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set document properties
        $spreadsheet->getProperties()
            ->setCreator(auth()->user()->name)
            ->setTitle("Analisis Laporan Keuangan")
            ->setSubject("Financial Analysis {$yearFrom}-{$yearTo}")
            ->setDescription("Generated by SIMKOP")
            ->setKeywords("analisis laporan keuangan koperasi")
            ->setCategory("Financial Analysis");

        $sheet->setTitle('Analisis');

        // Header
        $sheet->setCellValue('A1', 'ANALISIS LAPORAN KEUANGAN');
        $sheet->setCellValue('A2', "Periode {$yearFrom} - {$yearTo}");
        $sheet->setCellValue('A3', "Jenis Analisis: " . ucfirst($analysisType));

        // Style header
        $sheet->getStyle('A1:A3')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(16);

        $row = 5;

        // Generate analysis based on type
        switch ($analysisType) {
            case 'trend':
                $this->generateTrendAnalysis($sheet, $reports, $row);
                break;
            case 'comparison':
                $this->generateComparisonAnalysis($sheet, $reports, $row);
                break;
            case 'summary':
                $this->generateSummaryAnalysis($sheet, $reports, $row);
                break;
        }

        // Auto-size columns
        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Generate trend analysis
     */
    private function generateTrendAnalysis($sheet, $reports, int &$row): void
    {
        // Group reports by cooperative and report type
        $groupedReports = $reports->groupBy(['cooperative_id', 'report_type']);

        foreach ($groupedReports as $cooperativeId => $cooperativeReports) {
            $cooperative = $cooperativeReports->first()->first()->cooperative;

            $sheet->setCellValue('A' . $row, $cooperative->name);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;

            foreach ($cooperativeReports as $reportType => $typeReports) {
                $sheet->setCellValue('B' . $row, strtoupper(str_replace('_', ' ', $reportType)));
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
                $row++;

                // Column headers
                $sheet->setCellValue('C' . $row, 'Tahun');
                $sheet->setCellValue('D' . $row, 'Total Aset');
                $sheet->setCellValue('E' . $row, 'Total Pendapatan');
                $sheet->setCellValue('F' . $row, 'Laba Bersih');
                $sheet->setCellValue('G' . $row, 'Growth %');

                $sheet->getStyle('C' . $row . ':G' . $row)->getFont()->setBold(true);
                $row++;

                $previousTotal = 0;
                foreach ($typeReports->sortBy('reporting_year') as $report) {
                    $sheet->setCellValue('C' . $row, $report->reporting_year);

                    // Calculate totals based on report type
                    $currentTotal = $this->calculateReportTotal($report);
                    $sheet->setCellValue('D' . $row, $currentTotal);

                    // Calculate growth percentage
                    if ($previousTotal > 0) {
                        $growth = (($currentTotal - $previousTotal) / $previousTotal) * 100;
                        $sheet->setCellValue('G' . $row, $growth);
                    }

                    $previousTotal = $currentTotal;
                    $row++;
                }

                $row++; // Add spacing
            }

            $row++; // Add spacing between cooperatives
        }
    }

    /**
     * Generate comparison analysis
     */
    private function generateComparisonAnalysis($sheet, $reports, int &$row): void
    {
        // Group by year and report type
        $groupedReports = $reports->groupBy(['reporting_year', 'report_type']);

        foreach ($groupedReports as $year => $yearReports) {
            $sheet->setCellValue('A' . $row, "TAHUN {$year}");
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;

            foreach ($yearReports as $reportType => $typeReports) {
                $sheet->setCellValue('B' . $row, strtoupper(str_replace('_', ' ', $reportType)));
                $sheet->getStyle('B' . $row)->getFont()->setBold(true);
                $row++;

                // Column headers
                $sheet->setCellValue('C' . $row, 'Koperasi');
                $sheet->setCellValue('D' . $row, 'Total');
                $sheet->setCellValue('E' . $row, 'Ranking');

                $sheet->getStyle('C' . $row . ':E' . $row)->getFont()->setBold(true);
                $row++;

                // Sort by total and add ranking
                $sortedReports = $typeReports->sortByDesc(function ($report) {
                    return $this->calculateReportTotal($report);
                });

                $rank = 1;
                foreach ($sortedReports as $report) {
                    $sheet->setCellValue('C' . $row, $report->cooperative->name);
                    $sheet->setCellValue('D' . $row, $this->calculateReportTotal($report));
                    $sheet->setCellValue('E' . $row, $rank);

                    $rank++;
                    $row++;
                }

                $row++; // Add spacing
            }

            $row++; // Add spacing between years
        }
    }

    /**
     * Generate summary analysis
     */
    private function generateSummaryAnalysis($sheet, $reports, int &$row): void
    {
        // Overall statistics
        $sheet->setCellValue('A' . $row, 'RINGKASAN STATISTIK');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row += 2;

        $totalReports = $reports->count();
        $totalCooperatives = $reports->pluck('cooperative_id')->unique()->count();
        $reportTypes = $reports->pluck('report_type')->unique();

        $sheet->setCellValue('A' . $row, 'Total Laporan:');
        $sheet->setCellValue('B' . $row, $totalReports);
        $row++;

        $sheet->setCellValue('A' . $row, 'Total Koperasi:');
        $sheet->setCellValue('B' . $row, $totalCooperatives);
        $row++;

        $sheet->setCellValue('A' . $row, 'Jenis Laporan:');
        $sheet->setCellValue('B' . $row, $reportTypes->implode(', '));
        $row += 2;

        // Summary by report type
        $sheet->setCellValue('A' . $row, 'RINGKASAN PER JENIS LAPORAN');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('A' . $row, 'Jenis Laporan');
        $sheet->setCellValue('B' . $row, 'Jumlah');
        $sheet->setCellValue('C' . $row, 'Rata-rata Total');
        $sheet->setCellValue('D' . $row, 'Total Maksimum');
        $sheet->setCellValue('E' . $row, 'Total Minimum');

        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
        $row++;

        foreach ($reportTypes as $reportType) {
            $typeReports = $reports->where('report_type', $reportType);
            $totals = $typeReports->map(function ($report) {
                return $this->calculateReportTotal($report);
            })->filter();

            $sheet->setCellValue('A' . $row, strtoupper(str_replace('_', ' ', $reportType)));
            $sheet->setCellValue('B' . $row, $typeReports->count());

            if ($totals->isNotEmpty()) {
                $sheet->setCellValue('C' . $row, $totals->average());
                $sheet->setCellValue('D' . $row, $totals->max());
                $sheet->setCellValue('E' . $row, $totals->min());
            }

            $row++;
        }
    }

    /**
     * Calculate total for a report (simplified)
     */
    private function calculateReportTotal(FinancialReport $report): float
    {
        switch ($report->report_type) {
            case 'balance_sheet':
                return $report->balanceSheetAccounts
                    ->where('account_category', 'asset')
                    ->sum('current_year_amount');

            case 'income_statement':
                return $report->incomeStatementAccounts
                    ->where('account_category', 'revenue')
                    ->sum('current_year_amount');

            default:
                return 0;
        }
    }

    /**
     * Add charts to Excel (simplified implementation)
     */
    private function addChartsToExcel(Spreadsheet $spreadsheet, FinancialReport $report): void
    {
        // This is a placeholder for chart functionality
        // In a full implementation, you would use PhpSpreadsheet's chart features
        // to create visual representations of the financial data
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
                'cashFlowActivities'
            ])
            ->first();

        if (!$previousReport) {
            return null;
        }

        return [
            'previous_year' => $previousYear,
            'previous_report' => $previousReport
        ];
    }

    /**
     * Get previous year amount for balance sheet account
     */
    private function getPreviousYearAmount($previousReport, $currentAccount): float
    {
        if (!$previousReport) {
            return 0;
        }

        $previousAccount = $previousReport->balanceSheetAccounts
            ->where('account_code', $currentAccount->account_code)
            ->first();

        return $previousAccount ? $previousAccount->current_year_amount : 0;
    }

    /**
     * Get previous year amount for income statement account
     */
    private function getPreviousYearIncomeAmount($previousReport, $currentAccount): float
    {
        if (!$previousReport) {
            return 0;
        }

        $previousAccount = $previousReport->incomeStatementAccounts
            ->where('account_code', $currentAccount->account_code)
            ->first();

        return $previousAccount ? $previousAccount->current_year_amount : 0;
    }

    /**
     * Get previous year amount for cash flow activity
     */
    private function getPreviousYearCashFlowAmount($previousReport, $currentActivity): float
    {
        if (!$previousReport) {
            return 0;
        }

        $previousActivity = $previousReport->cashFlowActivities
            ->where('activity_code', $currentActivity->activity_code)
            ->first();

        return $previousActivity ? $previousActivity->current_year_amount : 0;
    }

    /**
     * Generate filename for Excel export
     */
    private function generateFilename(FinancialReport $report, string $format): string
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

        return "{$reportTypeName}_{$cooperativeName}_{$report->reporting_year}_" .
            now()->format('Y-m-d_H-i-s') . ".{$format}";
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
