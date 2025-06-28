<?php

namespace App\Services\Export;

use App\Models\Financial\FinancialReport;
use App\Services\AuditLogService;
use App\Jobs\GenerateReportPDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class BatchExportService
{
    public function __construct(
        private PDFExportService $pdfExportService,
        private ExcelExportService $excelExportService,
        private AuditLogService $auditLogService
    ) {}

    /**
     * Export multiple reports in batch.
     */
    public function exportBatch(array $reportIds, array $options = []): array
    {
        try {
            $reports = FinancialReport::whereIn('id', $reportIds)
                ->with(['cooperative'])
                ->get();

            if ($reports->isEmpty()) {
                throw new \Exception('No reports found for batch export.');
            }

            $exportFormat = $options['format'] ?? 'pdf';
            $createZip = $options['create_zip'] ?? true;

            $exportedFiles = [];
            $errors = [];

            foreach ($reports as $report) {
                try {
                    if ($exportFormat === 'pdf') {
                        $result = $this->pdfExportService->exportReport($report, $options);
                    } else {
                        $result = $this->excelExportService->exportReport($report, $options);
                    }

                    if ($result['success']) {
                        $exportedFiles[] = [
                            'report_id' => $report->id,
                            'filename' => $result['filename'],
                            'filepath' => $result['filepath'],
                            'file_size' => $result['file_size']
                        ];
                    } else {
                        $errors[] = [
                            'report_id' => $report->id,
                            'error' => $result['error']
                        ];
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'report_id' => $report->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $result = [
                'success' => !empty($exportedFiles),
                'exported_count' => count($exportedFiles),
                'error_count' => count($errors),
                'exported_files' => $exportedFiles,
                'errors' => $errors
            ];

            // Create ZIP file if requested and there are exported files
            if ($createZip && !empty($exportedFiles)) {
                $zipResult = $this->createZipArchive($exportedFiles, $options);
                $result['zip_file'] = $zipResult;
            }

            // Log the batch export
            $this->auditLogService->log(
                'batch_export_completed',
                "Batch export completed: {$result['exported_count']} files exported",
                [
                    'report_ids' => $reportIds,
                    'format' => $exportFormat,
                    'exported_count' => $result['exported_count'],
                    'error_count' => $result['error_count']
                ]
            );

            return $result;
        } catch (\Exception $e) {
            Log::error('Error in batch export', [
                'report_ids' => $reportIds,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exported_count' => 0,
                'error_count' => count($reportIds)
            ];
        }
    }

    /**
     * Export all reports for a cooperative and year.
     */
    public function exportCooperativeYear(int $cooperativeId, int $year, array $options = []): array
    {
        try {
            $reports = FinancialReport::where('cooperative_id', $cooperativeId)
                ->where('reporting_year', $year)
                ->where('status', 'approved')
                ->with(['cooperative'])
                ->get();

            if ($reports->isEmpty()) {
                throw new \Exception('No approved reports found for the specified cooperative and year.');
            }

            $reportIds = $reports->pluck('id')->toArray();
            $options['create_zip'] = true;
            $options['zip_name'] = $this->generateCooperativeYearZipName($reports->first(), $year);

            return $this->exportBatch($reportIds, $options);
        } catch (\Exception $e) {
            Log::error('Error exporting cooperative year reports', [
                'cooperative_id' => $cooperativeId,
                'year' => $year,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export reports by date range.
     */
    public function exportByDateRange(\DateTime $startDate, \DateTime $endDate, array $options = []): array
    {
        try {
            $reports = FinancialReport::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'approved')
                ->with(['cooperative'])
                ->get();

            if ($reports->isEmpty()) {
                throw new \Exception('No approved reports found in the specified date range.');
            }

            $reportIds = $reports->pluck('id')->toArray();
            $options['create_zip'] = true;
            $options['zip_name'] = $this->generateDateRangeZipName($startDate, $endDate);

            return $this->exportBatch($reportIds, $options);
        } catch (\Exception $e) {
            Log::error('Error exporting reports by date range', [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Schedule batch export as background job.
     */
    public function scheduleBatchExport(array $reportIds, array $options = []): array
    {
        try {
            $batchId = uniqid('batch_export_');
            $options['batch_id'] = $batchId;

            // Dispatch jobs for each report
            foreach ($reportIds as $reportId) {
                GenerateReportPDF::dispatch($reportId, $options)
                    ->onQueue('exports');
            }

            // Log the scheduled batch
            $this->auditLogService->log(
                'batch_export_scheduled',
                "Batch export scheduled with ID: {$batchId}",
                [
                    'batch_id' => $batchId,
                    'report_ids' => $reportIds,
                    'report_count' => count($reportIds)
                ]
            );

            return [
                'success' => true,
                'batch_id' => $batchId,
                'scheduled_count' => count($reportIds),
                'message' => 'Batch export has been scheduled. You will be notified when complete.'
            ];
        } catch (\Exception $e) {
            Log::error('Error scheduling batch export', [
                'report_ids' => $reportIds,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get batch export status.
     */
    public function getBatchStatus(string $batchId): array
    {
        try {
            // Check for completed files in the batch directory
            $batchDirectory = "exports/batch/{$batchId}";
            $files = Storage::files($batchDirectory);

            $status = [
                'batch_id' => $batchId,
                'status' => 'processing',
                'completed_files' => count($files),
                'files' => []
            ];

            foreach ($files as $file) {
                $status['files'][] = [
                    'filename' => basename($file),
                    'filepath' => $file,
                    'file_size' => Storage::size($file),
                    'download_url' => Storage::url($file)
                ];
            }

            // Check if batch is complete (this would typically be tracked in database)
            $expectedCount = $this->getExpectedFileCount($batchId);
            if (count($files) >= $expectedCount) {
                $status['status'] = 'completed';
            }

            return $status;
        } catch (\Exception $e) {
            Log::error('Error getting batch status', [
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);

            return [
                'batch_id' => $batchId,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create ZIP archive from exported files.
     */
    private function createZipArchive(array $exportedFiles, array $options): array
    {
        try {
            $zipName = $options['zip_name'] ?? $this->generateZipName($options);
            $zipDirectory = 'exports/zip/' . date('Y/m');
            $zipPath = $zipDirectory . '/' . $zipName;

            // Ensure directory exists
            Storage::makeDirectory($zipDirectory);

            // Create temporary ZIP file
            $tempZipPath = tempnam(sys_get_temp_dir(), 'batch_export_zip');
            $zip = new ZipArchive();

            if ($zip->open($tempZipPath, ZipArchive::CREATE) !== TRUE) {
                throw new \Exception('Cannot create ZIP archive');
            }

            // Add files to ZIP
            foreach ($exportedFiles as $file) {
                $fileContent = Storage::get($file['filepath']);
                $zip->addFromString($file['filename'], $fileContent);
            }

            $zip->close();

            // Move ZIP to storage
            Storage::put($zipPath, file_get_contents($tempZipPath));
            unlink($tempZipPath);

            return [
                'filename' => $zipName,
                'filepath' => $zipPath,
                'download_url' => Storage::url($zipPath),
                'file_size' => Storage::size($zipPath),
                'file_count' => count($exportedFiles)
            ];
        } catch (\Exception $e) {
            Log::error('Error creating ZIP archive', [
                'exported_files_count' => count($exportedFiles),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Generate ZIP name for cooperative year export.
     */
    private function generateCooperativeYearZipName(FinancialReport $report, int $year): string
    {
        $cooperativeName = str_replace(' ', '-', $report->cooperative->name);
        $timestamp = now()->format('Y-m-d_H-i-s');

        return "{$cooperativeName}_laporan_keuangan_{$year}_{$timestamp}.zip";
    }

    /**
     * Generate ZIP name for date range export.
     */
    private function generateDateRangeZipName(\DateTime $startDate, \DateTime $endDate): string
    {
        $start = $startDate->format('Y-m-d');
        $end = $endDate->format('Y-m-d');
        $timestamp = now()->format('Y-m-d_H-i-s');

        return "laporan_keuangan_{$start}_to_{$end}_{$timestamp}.zip";
    }

    /**
     * Generate default ZIP name.
     */
    private function generateZipName(array $options): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $format = $options['format'] ?? 'pdf';

        return "batch_export_{$format}_{$timestamp}.zip";
    }

    /**
     * Get expected file count for batch (placeholder implementation).
     */
    private function getExpectedFileCount(string $batchId): int
    {
        // This would typically be stored in database when batch is created
        // For now, return a default value
        return 1;
    }

    /**
     * Clean up old export files.
     */
    public function cleanupOldExports(int $daysOld = 30): array
    {
        try {
            $cutoffDate = now()->subDays($daysOld);
            $directories = ['exports/pdf', 'exports/excel', 'exports/zip', 'exports/batch'];

            $deletedFiles = 0;
            $deletedSize = 0;

            foreach ($directories as $directory) {
                $files = Storage::allFiles($directory);

                foreach ($files as $file) {
                    $lastModified = Storage::lastModified($file);

                    if ($lastModified < $cutoffDate->timestamp) {
                        $fileSize = Storage::size($file);
                        Storage::delete($file);

                        $deletedFiles++;
                        $deletedSize += $fileSize;
                    }
                }
            }

            // Log the cleanup
            $this->auditLogService->log(
                'export_files_cleaned',
                "Cleaned up {$deletedFiles} old export files",
                [
                    'deleted_files' => $deletedFiles,
                    'deleted_size' => $deletedSize,
                    'cutoff_date' => $cutoffDate->toDateString()
                ]
            );

            return [
                'success' => true,
                'deleted_files' => $deletedFiles,
                'deleted_size' => $deletedSize,
                'cutoff_date' => $cutoffDate->toDateString()
            ];
        } catch (\Exception $e) {
            Log::error('Error cleaning up old exports', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get export statistics.
     */
    public function getExportStatistics(): array
    {
        try {
            $directories = ['exports/pdf', 'exports/excel', 'exports/zip'];
            $stats = [
                'total_files' => 0,
                'total_size' => 0,
                'by_type' => []
            ];

            foreach ($directories as $directory) {
                $type = basename($directory);
                $files = Storage::allFiles($directory);
                $typeSize = 0;

                foreach ($files as $file) {
                    $typeSize += Storage::size($file);
                }

                $stats['by_type'][$type] = [
                    'file_count' => count($files),
                    'total_size' => $typeSize
                ];

                $stats['total_files'] += count($files);
                $stats['total_size'] += $typeSize;
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error('Error getting export statistics', [
                'error' => $e->getMessage()
            ]);

            return [
                'error' => $e->getMessage()
            ];
        }
    }
}
