<?php

namespace App\Services\Export;

use App\Models\Financial\FinancialReport;
use App\Services\AuditLogService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ExcelExportService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Export financial report to Excel.
     */
    public function exportReport(FinancialReport $report, array $options = []): array
    {
        try {
            // Load necessary relationships
            $report->load($this->getRelationshipsForReportType($report->report_type));
            $report->load('cooperative');

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();

            // Generate Excel content based on report type
            $this->generateExcelContent($spreadsheet, $report, $options);

            // Save Excel file
            $filename = $this->generateFilename($report, $options);
            $filepath = $this->saveExcel($spreadsheet, $filename);

            // Log the export
            $this->auditLogService->log(
                'report_exported_excel',
                "Financial report exported to Excel: {$filename}",
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
            Log::error('Error exporting report to Excel', [
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
     * Generate Excel content based on report type.
     */
    private function generateExcelContent(Spreadsheet $spreadsheet, FinancialReport $report, array $options): void
    {
        switch ($report->report_type) {
            case 'balance_sheet':
                $this->generateBalanceSheetExcel($spreadsheet, $report, $options);
                break;
            case 'income_statement':
                $this->generateIncomeStatementExcel($spreadsheet, $report, $options);
                break;
            case 'cash_flow':
                $this->generateCashFlowExcel($spreadsheet, $report, $options);
                break;
            case 'member_savings':
                $this->generateMemberSavingsExcel($spreadsheet, $report, $options);
                break;
            default:
                $this->generateGenericExcel($spreadsheet, $report, $options);
        }
    }

    /**
     * Generate balance sheet Excel.
     */
    private function generateBalanceSheetExcel(Spreadsheet $spreadsheet, FinancialReport $report, array $options): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Neraca');

        // Header
        $this->addReportHeader($sheet, $report, 'NERACA');

        $row = 6;

        // Assets
        $sheet->setCellValue("A{$row}", 'ASET');
        $this->applyHeaderStyle($sheet, "A{$row}:D{$row}");
        $row++;

        $assets = $report->balanceSheetAccounts->where('account_category', 'asset')->sortBy('sort_order');
        $totalAssets = 0;

        foreach ($assets as $account) {
            $sheet->setCellValue("A{$row}", $account->account_code);
            $sheet->setCellValue("B{$row}", $account->account_name);
            $sheet->setCellValue("C{$row}", $account->current_year_amount);
            $sheet->setCellValue("D{$row}", $account->previous_year_amount ?? 0);

            if (!$account->is_subtotal) {
                $totalAssets += $account->current_year_amount;
            }

            $this->applyDataStyle($sheet, "A{$row}:D{$row}", $account->is_subtotal);
            $row++;
        }

        // Total Assets
        $sheet->setCellValue("B{$row}", 'TOTAL ASET');
        $sheet->setCellValue("C{$row}", $totalAssets);
        $this->applyTotalStyle($sheet, "A{$row}:D{$row}");
        $row += 2;

        // Liabilities
        $sheet->setCellValue("A{$row}", 'KEWAJIBAN');
        $this->applyHeaderStyle($sheet, "A{$row}:D{$row}");
        $row++;

        $liabilities = $report->balanceSheetAccounts->where('account_category', 'liability')->sortBy('sort_order');
        $totalLiabilities = 0;

        foreach ($liabilities as $account) {
            $sheet->setCellValue("A{$row}", $account->account_code);
            $sheet->setCellValue("B{$row}", $account->account_name);
            $sheet->setCellValue("C{$row}", $account->current_year_amount);
            $sheet->setCellValue("D{$row}", $account->previous_year_amount ?? 0);

            if (!$account->is_subtotal) {
                $totalLiabilities += $account->current_year_amount;
            }

            $this->applyDataStyle($sheet, "A{$row}:D{$row}", $account->is_subtotal);
            $row++;
        }

        // Total Liabilities
        $sheet->setCellValue("B{$row}", 'TOTAL KEWAJIBAN');
        $sheet->setCellValue("C{$row}", $totalLiabilities);
        $this->applyTotalStyle($sheet, "A{$row}:D{$row}");
        $row += 2;

        // Equity
        $sheet->setCellValue("A{$row}", 'EKUITAS');
        $this->applyHeaderStyle($sheet, "A{$row}:D{$row}");
        $row++;

        $equity = $report->balanceSheetAccounts->where('account_category', 'equity')->sortBy('sort_order');
        $totalEquity = 0;

        foreach ($equity as $account) {
            $sheet->setCellValue("A{$row}", $account->account_code);
            $sheet->setCellValue("B{$row}", $account->account_name);
            $sheet->setCellValue("C{$row}", $account->current_year_amount);
            $sheet->setCellValue("D{$row}", $account->previous_year_amount ?? 0);

            if (!$account->is_subtotal) {
                $totalEquity += $account->current_year_amount;
            }

            $this->applyDataStyle($sheet, "A{$row}:D{$row}", $account->is_subtotal);
            $row++;
        }

        // Total Equity
        $sheet->setCellValue("B{$row}", 'TOTAL EKUITAS');
        $sheet->setCellValue("C{$row}", $totalEquity);
        $this->applyTotalStyle($sheet, "A{$row}:D{$row}");
        $row++;

        // Total Liabilities + Equity
        $sheet->setCellValue("B{$row}", 'TOTAL KEWAJIBAN + EKUITAS');
        $sheet->setCellValue("C{$row}", $totalLiabilities + $totalEquity);
        $this->applyTotalStyle($sheet, "A{$row}:D{$row}");

        // Auto-size columns
        $this->autoSizeColumns($sheet, ['A', 'B', 'C', 'D']);
    }

    /**
     * Generate member savings Excel.
     */
    private function generateMemberSavingsExcel(Spreadsheet $spreadsheet, FinancialReport $report, array $options): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Simpanan Anggota');

        // Header
        $this->addReportHeader($sheet, $report, 'LAPORAN SIMPANAN ANGGOTA');

        $row = 6;

        // Column headers
        $headers = [
            'A' => 'ID Anggota',
            'B' => 'Nama Anggota',
            'C' => 'Jenis Simpanan',
            'D' => 'Saldo Awal',
            'E' => 'Setoran',
            'F' => 'Penarikan',
            'G' => 'Bunga',
            'H' => 'Saldo Akhir'
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
        }
        $this->applyHeaderStyle($sheet, "A{$row}:H{$row}");
        $row++;

        // Data
        $memberSavings = $report->memberSavings;
        $totals = [
            'beginning_balance' => 0,
            'deposits' => 0,
            'withdrawals' => 0,
            'interest_earned' => 0,
            'ending_balance' => 0
        ];

        foreach ($memberSavings as $saving) {
            $sheet->setCellValue("A{$row}", $saving->member_id);
            $sheet->setCellValue("B{$row}", $saving->member_name);
            $sheet->setCellValue("C{$row}", $this->formatSavingsType($saving->savings_type));
            $sheet->setCellValue("D{$row}", $saving->beginning_balance);
            $sheet->setCellValue("E{$row}", $saving->deposits);
            $sheet->setCellValue("F{$row}", $saving->withdrawals);
            $sheet->setCellValue("G{$row}", $saving->interest_earned);
            $sheet->setCellValue("H{$row}", $saving->ending_balance);

            $totals['beginning_balance'] += $saving->beginning_balance;
            $totals['deposits'] += $saving->deposits;
            $totals['withdrawals'] += $saving->withdrawals;
            $totals['interest_earned'] += $saving->interest_earned;
            $totals['ending_balance'] += $saving->ending_balance;

            $this->applyDataStyle($sheet, "A{$row}:H{$row}");
            $row++;
        }

        // Totals
        $sheet->setCellValue("C{$row}", 'TOTAL');
        $sheet->setCellValue("D{$row}", $totals['beginning_balance']);
        $sheet->setCellValue("E{$row}", $totals['deposits']);
        $sheet->setCellValue("F{$row}", $totals['withdrawals']);
        $sheet->setCellValue("G{$row}", $totals['interest_earned']);
        $sheet->setCellValue("H{$row}", $totals['ending_balance']);
        $this->applyTotalStyle($sheet, "A{$row}:H{$row}");

        // Auto-size columns
        $this->autoSizeColumns($sheet, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);
    }

    /**
     * Add report header to sheet.
     */
    private function addReportHeader($sheet, FinancialReport $report, string $title): void
    {
        // Cooperative name
        $sheet->setCellValue('A1', $report->cooperative->name);
        $sheet->mergeCells('A1:D1');
        $this->applyTitleStyle($sheet, 'A1');

        // Report title
        $sheet->setCellValue('A2', $title);
        $sheet->mergeCells('A2:D2');
        $this->applyTitleStyle($sheet, 'A2');

        // Period
        $sheet->setCellValue('A3', "Periode: {$report->reporting_period} {$report->reporting_year}");
        $sheet->mergeCells('A3:D3');

        // Generated info
        $sheet->setCellValue('A4', 'Dibuat pada: ' . now()->format('d/m/Y H:i:s'));
        $sheet->mergeCells('A4:D4');
    }

    /**
     * Apply title style.
     */
    private function applyTitleStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER
            ]
        ]);
    }

    /**
     * Apply header style.
     */
    private function applyHeaderStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);
    }

    /**
     * Apply data style.
     */
    private function applyDataStyle($sheet, string $range, bool $isSubtotal = false): void
    {
        $style = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ];

        if ($isSubtotal) {
            $style['font'] = ['bold' => true];
            $style['fill'] = [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E7E6E6']
            ];
        }

        $sheet->getStyle($range)->applyFromArray($style);

        // Format currency columns
        $currencyColumns = ['C', 'D', 'E', 'F', 'G', 'H'];
        foreach ($currencyColumns as $col) {
            if (strpos($range, $col) !== false) {
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0');
            }
        }
    }

    /**
     * Apply total style.
     */
    private function applyTotalStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9D9D9']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THICK
                ]
            ]
        ]);

        // Format currency columns
        $currencyColumns = ['C', 'D'];
        foreach ($currencyColumns as $col) {
            if (strpos($range, $col) !== false) {
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0');
            }
        }
    }

    /**
     * Auto-size columns.
     */
    private function autoSizeColumns($sheet, array $columns): void
    {
        foreach ($columns as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * Save Excel file.
     */
    private function saveExcel(Spreadsheet $spreadsheet, string $filename): string
    {
        $directory = 'exports/excel/' . date('Y/m');
        $filepath = $directory . '/' . $filename;

        // Ensure directory exists
        Storage::makeDirectory($directory);

        // Create writer and save
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_export');
        $writer->save($tempFile);

        // Move to storage
        Storage::put($filepath, file_get_contents($tempFile));
        unlink($tempFile);

        return $filepath;
    }

    /**
     * Generate filename.
     */
    private function generateFilename(FinancialReport $report, array $options): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $reportType = str_replace('_', '-', $report->report_type);
        $cooperativeName = str_replace(' ', '-', $report->cooperative->name);

        return "{$cooperativeName}_{$reportType}_{$report->reporting_year}_{$timestamp}.xlsx";
    }

    /**
     * Format savings type for display.
     */
    private function formatSavingsType(string $savingsType): string
    {
        return match ($savingsType) {
            'simpanan_pokok' => 'Simpanan Pokok',
            'simpanan_wajib' => 'Simpanan Wajib',
            'simpanan_sukarela' => 'Simpanan Sukarela',
            default => ucfirst(str_replace('_', ' ', $savingsType))
        };
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
