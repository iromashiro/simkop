<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Cooperative;
use App\Models\Financial\FinancialReport;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use ZipArchive;

class BatchExportController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_dinas'); // Only admin dinas can do batch exports
        $this->middleware('throttle:batch-exports')->only(['export', 'download']);
    }

    /**
     * Show batch export form
     */
    public function index(Request $request)
    {
        $cooperatives = Cooperative::select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        $availableYears = FinancialReport::distinct()
            ->pluck('reporting_year')
            ->sort()
            ->values();

        $reportTypes = [
            'balance_sheet' => 'Neraca',
            'income_statement' => 'Laporan Laba Rugi',
            'equity_changes' => 'Laporan Perubahan Ekuitas',
            'cash_flow' => 'Laporan Arus Kas',
            'member_savings' => 'Laporan Simpanan Anggota',
            'member_receivables' => 'Laporan Piutang Anggota',
            'npl_receivables' => 'Laporan Piutang NPL',
            'shu_distribution' => 'Laporan Distribusi SHU',
            'budget_plan' => 'Rencana Anggaran',
            'notes_to_financial' => 'Catatan atas Laporan Keuangan'
        ];

        return view('reports.batch-export.index', compact(
            'cooperatives',
            'availableYears',
            'reportTypes'
        ));
    }

    /**
     * Process batch export request
     */
    public function export(Request $request): Response
    {
        try {
            $request->validate([
                'export_type' => 'required|string|in:cooperative,year,report_type,custom',
                'format' => 'required|string|in:pdf,excel,both',
                'cooperative_ids' => 'sometimes|array',
                'cooperative_ids.*' => 'exists:cooperatives,id',
                'years' => 'sometimes|array',
                'years.*' => 'integer|min:2020|max:' . (now()->year + 1),
                'report_types' => 'sometimes|array',
                'report_types.*' => 'string|in:balance_sheet,income_statement,equity_changes,cash_flow,member_savings,member_receivables,npl_receivables,shu_distribution,budget_plan,notes_to_financial',
                'status' => 'sometimes|string|in:draft,submitted,approved,rejected',
                'include_comparison' => 'sometimes|boolean',
                'include_notes' => 'sometimes|boolean',
                'template' => 'sometimes|string|in:standard,detailed,summary',
                'async_processing' => 'sometimes|boolean'
            ]);

            $exportType = $request->input('export_type');
            $format = $request->input('format');
            $asyncProcessing = $request->boolean('async_processing', true);

            // Build query based on export type
            $query = $this->buildExportQuery($request, $exportType);

            // Get reports to export
            $reports = $query->with([
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
            ])->get();

            if ($reports->isEmpty()) {
                return redirect()->back()
                    ->with('error', 'Tidak ada laporan yang ditemukan untuk kriteria yang dipilih.');
            }

            // Check export limits
            if ($reports->count() > 100) {
                return redirect()->back()
                    ->with('error', 'Terlalu banyak laporan untuk diekspor sekaligus. Maksimal 100 laporan.');
            }

            $exportOptions = [
                'format' => $format,
                'include_comparison' => $request->boolean('include_comparison', false),
                'include_notes' => $request->boolean('include_notes', true),
                'template' => $request->input('template', 'standard'),
                'export_type' => $exportType
            ];

            if ($asyncProcessing && $reports->count() > 10) {
                // Process asynchronously for large batches
                return $this->processAsyncExport($reports, $exportOptions);
            } else {
                // Process synchronously for small batches
                return $this->processSyncExport($reports, $exportOptions);
            }
        } catch (\Exception $e) {
            Log::error('Error in batch export', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal memproses ekspor batch: ' . $e->getMessage());
        }
    }

    /**
     * Download completed batch export
     */
    public function download(Request $request, string $batchId): Response
    {
        try {
            $request->validate([
                'format' => 'sometimes|string|in:pdf,excel,zip'
            ]);

            $format = $request->input('format', 'zip');
            $filePath = "exports/batch/{$batchId}.{$format}";

            if (!Storage::disk('local')->exists($filePath)) {
                abort(404, 'File ekspor tidak ditemukan atau sudah kedaluwarsa.');
            }

            // Log download activity
            $this->auditLogService->log(
                'batch_export_download',
                'Batch export file downloaded',
                [
                    'batch_id' => $batchId,
                    'format' => $format,
                    'file_path' => $filePath
                ]
            );

            $filename = "batch_export_{$batchId}_{$format}_" . now()->format('Y-m-d_H-i-s') . ".{$format}";

            return Storage::disk('local')->download($filePath, $filename);
        } catch (\Exception $e) {
            Log::error('Error downloading batch export', [
                'user_id' => auth()->id(),
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal mengunduh file ekspor.');
        }
    }

    /**
     * Get batch export status
     */
    public function status(Request $request, string $batchId)
    {
        try {
            $statusFile = "exports/batch/status/{$batchId}.json";

            if (!Storage::disk('local')->exists($statusFile)) {
                return response()->json([
                    'status' => 'not_found',
                    'message' => 'Batch export tidak ditemukan'
                ], 404);
            }

            $status = json_decode(Storage::disk('local')->get($statusFile), true);

            return response()->json($status);
        } catch (\Exception $e) {
            Log::error('Error getting batch export status', [
                'user_id' => auth()->id(),
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mendapatkan status ekspor'
            ], 500);
        }
    }

    /**
     * Cancel batch export
     */
    public function cancel(Request $request, string $batchId)
    {
        try {
            // Update status to cancelled
            $statusFile = "exports/batch/status/{$batchId}.json";

            if (Storage::disk('local')->exists($statusFile)) {
                $status = json_decode(Storage::disk('local')->get($statusFile), true);
                $status['status'] = 'cancelled';
                $status['cancelled_at'] = now()->toISOString();
                $status['cancelled_by'] = auth()->id();

                Storage::disk('local')->put($statusFile, json_encode($status));
            }

            // Log cancellation
            $this->auditLogService->log(
                'batch_export_cancelled',
                'Batch export cancelled',
                [
                    'batch_id' => $batchId,
                    'cancelled_by' => auth()->id()
                ]
            );

            return response()->json([
                'status' => 'cancelled',
                'message' => 'Ekspor batch berhasil dibatalkan'
            ]);
        } catch (\Exception $e) {
            Log::error('Error cancelling batch export', [
                'user_id' => auth()->id(),
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membatalkan ekspor'
            ], 500);
        }
    }

    /**
     * List user's batch exports
     */
    public function history(Request $request)
    {
        try {
            $statusFiles = Storage::disk('local')->files('exports/batch/status');
            $exports = [];

            foreach ($statusFiles as $file) {
                $status = json_decode(Storage::disk('local')->get($file), true);

                // Only show exports created by current user
                if ($status['created_by'] === auth()->id()) {
                    $exports[] = $status;
                }
            }

            // Sort by created date (newest first)
            usort($exports, function ($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            // Paginate manually
            $page = $request->input('page', 1);
            $perPage = 10;
            $offset = ($page - 1) * $perPage;
            $paginatedExports = array_slice($exports, $offset, $perPage);

            return view('reports.batch-export.history', [
                'exports' => $paginatedExports,
                'total' => count($exports),
                'currentPage' => $page,
                'perPage' => $perPage
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting batch export history', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal memuat riwayat ekspor.');
        }
    }

    /**
     * Build export query based on export type
     */
    private function buildExportQuery(Request $request, string $exportType)
    {
        $query = FinancialReport::query();

        switch ($exportType) {
            case 'cooperative':
                $cooperativeIds = $request->input('cooperative_ids', []);
                if (!empty($cooperativeIds)) {
                    $query->whereIn('cooperative_id', $cooperativeIds);
                }
                break;

            case 'year':
                $years = $request->input('years', []);
                if (!empty($years)) {
                    $query->whereIn('reporting_year', $years);
                }
                break;

            case 'report_type':
                $reportTypes = $request->input('report_types', []);
                if (!empty($reportTypes)) {
                    $query->whereIn('report_type', $reportTypes);
                }
                break;

            case 'custom':
                // Apply all filters for custom export
                $cooperativeIds = $request->input('cooperative_ids', []);
                if (!empty($cooperativeIds)) {
                    $query->whereIn('cooperative_id', $cooperativeIds);
                }

                $years = $request->input('years', []);
                if (!empty($years)) {
                    $query->whereIn('reporting_year', $years);
                }

                $reportTypes = $request->input('report_types', []);
                if (!empty($reportTypes)) {
                    $query->whereIn('report_type', $reportTypes);
                }
                break;
        }

        // Apply status filter
        $status = $request->input('status');
        if ($status) {
            $query->where('status', $status);
        } else {
            // Default to approved reports only
            $query->where('status', 'approved');
        }

        return $query->orderBy('cooperative_id')
            ->orderBy('reporting_year', 'desc')
            ->orderBy('report_type');
    }

    /**
     * Process synchronous export for small batches
     */
    private function processSyncExport($reports, array $options): Response
    {
        try {
            $batchId = Str::uuid();
            $format = $options['format'];

            // Create status tracking
            $this->createBatchStatus($batchId, $reports->count(), $options);

            if ($format === 'both') {
                // Create both PDF and Excel, then zip them
                $zipPath = $this->createBothFormatsZip($reports, $options, $batchId);
                $downloadFormat = 'zip';
            } elseif ($format === 'pdf') {
                $zipPath = $this->createPDFBatch($reports, $options, $batchId);
                $downloadFormat = 'zip';
            } elseif ($format === 'excel') {
                $zipPath = $this->createExcelBatch($reports, $options, $batchId);
                $downloadFormat = 'zip';
            }

            // Update status to completed
            $this->updateBatchStatus($batchId, 'completed', [
                'completed_at' => now()->toISOString(),
                'file_path' => $zipPath,
                'download_url' => route('reports.batch-export.download', ['batchId' => $batchId, 'format' => $downloadFormat])
            ]);

            // Log the export activity
            $this->auditLogService->log(
                'batch_export_completed',
                'Batch export completed synchronously',
                [
                    'batch_id' => $batchId,
                    'report_count' => $reports->count(),
                    'format' => $format,
                    'export_type' => $options['export_type']
                ]
            );

            return redirect()->route('reports.batch-export.download', [
                'batchId' => $batchId,
                'format' => $downloadFormat
            ]);
        } catch (\Exception $e) {
            if (isset($batchId)) {
                $this->updateBatchStatus($batchId, 'failed', [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString()
                ]);
            }

            throw $e;
        }
    }

    /**
     * Process asynchronous export for large batches
     */
    private function processAsyncExport($reports, array $options): Response
    {
        try {
            $batchId = Str::uuid();

            // Create status tracking
            $this->createBatchStatus($batchId, $reports->count(), $options);

            // Queue the batch export job
            // Note: You would need to create a BatchExportJob class
            // Queue::push(new BatchExportJob($batchId, $reports->pluck('id')->toArray(), $options));

            // For now, we'll simulate async processing
            $this->simulateAsyncProcessing($batchId, $reports, $options);

            // Log the export initiation
            $this->auditLogService->log(
                'batch_export_initiated',
                'Batch export initiated asynchronously',
                [
                    'batch_id' => $batchId,
                    'report_count' => $reports->count(),
                    'format' => $options['format'],
                    'export_type' => $options['export_type']
                ]
            );

            return redirect()->route('reports.batch-export.status', $batchId)
                ->with('success', 'Ekspor batch telah dimulai. Anda akan mendapat notifikasi ketika selesai.');
        } catch (\Exception $e) {
            if (isset($batchId)) {
                $this->updateBatchStatus($batchId, 'failed', [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString()
                ]);
            }

            throw $e;
        }
    }

    /**
     * Create PDF batch export
     */
    private function createPDFBatch($reports, array $options, string $batchId): string
    {
        $zipPath = "exports/batch/{$batchId}.zip";
        $zip = new ZipArchive();

        if ($zip->open(Storage::disk('local')->path($zipPath), ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create ZIP file');
        }

        $processed = 0;
        foreach ($reports as $report) {
            try {
                // Generate PDF for each report
                $pdfContent = $this->generateReportPDF($report, $options);
                $filename = $this->generatePDFFilename($report);

                $zip->addFromString($filename, $pdfContent);

                $processed++;
                $this->updateBatchProgress($batchId, $processed, $reports->count());
            } catch (\Exception $e) {
                Log::error('Error generating PDF for report', [
                    'report_id' => $report->id,
                    'batch_id' => $batchId,
                    'error' => $e->getMessage()
                ]);

                // Add error file to zip
                $errorContent = "Error generating PDF: " . $e->getMessage();
                $zip->addFromString("ERROR_{$report->id}.txt", $errorContent);
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Create Excel batch export
     */
    private function createExcelBatch($reports, array $options, string $batchId): string
    {
        $zipPath = "exports/batch/{$batchId}.zip";
        $zip = new ZipArchive();

        if ($zip->open(Storage::disk('local')->path($zipPath), ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create ZIP file');
        }

        $processed = 0;
        foreach ($reports as $report) {
            try {
                // Generate Excel for each report
                $excelContent = $this->generateReportExcel($report, $options);
                $filename = $this->generateExcelFilename($report);

                $zip->addFromString($filename, $excelContent);

                $processed++;
                $this->updateBatchProgress($batchId, $processed, $reports->count());
            } catch (\Exception $e) {
                Log::error('Error generating Excel for report', [
                    'report_id' => $report->id,
                    'batch_id' => $batchId,
                    'error' => $e->getMessage()
                ]);

                // Add error file to zip
                $errorContent = "Error generating Excel: " . $e->getMessage();
                $zip->addFromString("ERROR_{$report->id}.txt", $errorContent);
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Create both PDF and Excel formats in ZIP
     */
    private function createBothFormatsZip($reports, array $options, string $batchId): string
    {
        $zipPath = "exports/batch/{$batchId}.zip";
        $zip = new ZipArchive();

        if ($zip->open(Storage::disk('local')->path($zipPath), ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create ZIP file');
        }

        $processed = 0;
        $totalOperations = $reports->count() * 2; // PDF + Excel for each report

        foreach ($reports as $report) {
            try {
                // Generate PDF
                $pdfContent = $this->generateReportPDF($report, $options);
                $pdfFilename = "PDF/" . $this->generatePDFFilename($report);
                $zip->addFromString($pdfFilename, $pdfContent);

                $processed++;
                $this->updateBatchProgress($batchId, $processed, $totalOperations);

                // Generate Excel
                $excelContent = $this->generateReportExcel($report, $options);
                $excelFilename = "Excel/" . $this->generateExcelFilename($report);
                $zip->addFromString($excelFilename, $excelContent);

                $processed++;
                $this->updateBatchProgress($batchId, $processed, $totalOperations);
            } catch (\Exception $e) {
                Log::error('Error generating both formats for report', [
                    'report_id' => $report->id,
                    'batch_id' => $batchId,
                    'error' => $e->getMessage()
                ]);

                // Add error file to zip
                $errorContent = "Error generating report: " . $e->getMessage();
                $zip->addFromString("ERROR_{$report->id}.txt", $errorContent);

                $processed += 2; // Count as 2 operations even if failed
                $this->updateBatchProgress($batchId, $processed, $totalOperations);
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Generate PDF content for a report (simplified)
     */
    private function generateReportPDF(FinancialReport $report, array $options): string
    {
        // This would integrate with PDFExportController
        // For now, return a placeholder
        return "PDF content for report {$report->id} would be generated here";
    }

    /**
     * Generate Excel content for a report (simplified)
     */
    private function generateReportExcel(FinancialReport $report, array $options): string
    {
        // This would integrate with ExcelExportController
        // For now, return a placeholder
        return "Excel content for report {$report->id} would be generated here";
    }

    /**
     * Generate PDF filename
     */
    private function generatePDFFilename(FinancialReport $report): string
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

        return "{$reportTypeName}_{$cooperativeName}_{$report->reporting_year}.pdf";
    }

    /**
     * Generate Excel filename
     */
    private function generateExcelFilename(FinancialReport $report): string
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

        return "{$reportTypeName}_{$cooperativeName}_{$report->reporting_year}.xlsx";
    }

    /**
     * Create batch status tracking
     */
    private function createBatchStatus(string $batchId, int $totalReports, array $options): void
    {
        $status = [
            'batch_id' => $batchId,
            'status' => 'processing',
            'total_reports' => $totalReports,
            'processed_reports' => 0,
            'progress_percentage' => 0,
            'format' => $options['format'],
            'export_type' => $options['export_type'],
            'created_by' => auth()->id(),
            'created_at' => now()->toISOString(),
            'estimated_completion' => now()->addMinutes($totalReports * 0.5)->toISOString() // Rough estimate
        ];

        Storage::disk('local')->put(
            "exports/batch/status/{$batchId}.json",
            json_encode($status)
        );
    }

    /**
     * Update batch status
     */
    private function updateBatchStatus(string $batchId, string $status, array $additionalData = []): void
    {
        $statusFile = "exports/batch/status/{$batchId}.json";

        if (Storage::disk('local')->exists($statusFile)) {
            $currentStatus = json_decode(Storage::disk('local')->get($statusFile), true);
            $currentStatus['status'] = $status;
            $currentStatus = array_merge($currentStatus, $additionalData);

            Storage::disk('local')->put($statusFile, json_encode($currentStatus));
        }
    }

    /**
     * Update batch progress
     */
    private function updateBatchProgress(string $batchId, int $processed, int $total): void
    {
        $percentage = round(($processed / $total) * 100, 2);

        $this->updateBatchStatus($batchId, 'processing', [
            'processed_reports' => $processed,
            'progress_percentage' => $percentage,
            'updated_at' => now()->toISOString()
        ]);
    }

    /**
     * Simulate async processing (placeholder)
     */
    private function simulateAsyncProcessing(string $batchId, $reports, array $options): void
    {
        // In a real implementation, this would be handled by a queued job
        // For demonstration purposes, we'll just update the status

        $this->updateBatchStatus($batchId, 'queued', [
            'queued_at' => now()->toISOString(),
            'estimated_start' => now()->addMinutes(1)->toISOString()
        ]);
    }
}
