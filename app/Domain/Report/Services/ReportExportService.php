<?php
// app/Domain/Report/Services/ReportExportService.php
namespace App\Domain\Report\Services;

use App\Domain\Report\DTOs\ReportResultDTO;
use App\Domain\Report\DTOs\ExportRequestDTO;
use App\Domain\Report\Contracts\ExportDriverInterface;
use App\Domain\Report\Exceptions\ExportException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;

/**
 * PRODUCTION READY: Report Export Service with multiple format support
 * SRS Reference: Section 3.4.10 - Report Export Requirements
 */
class ReportExportService
{
    private array $drivers = [];

    public function __construct()
    {
        $this->registerDrivers();
    }

    /**
     * Export report to specified format
     */
    public function export(ReportResultDTO $report, ExportRequestDTO $request): Response
    {
        try {
            Log::info('Starting report export', [
                'report_title' => $report->title,
                'format' => $request->format,
                'user_id' => auth()->id(),
                'cooperative_id' => $report->cooperativeId,
            ]);

            // Validate export request
            $this->validateExportRequest($request);

            // Get appropriate driver
            $driver = $this->getDriver($request->format);

            // Generate export
            $exportResult = $driver->export($report, $request);

            // Log successful export
            Log::info('Report export completed', [
                'report_title' => $report->title,
                'format' => $request->format,
                'file_size' => $exportResult->getSize(),
                'generation_time' => $exportResult->getGenerationTime(),
            ]);

            return $exportResult->toResponse();
        } catch (\Exception $e) {
            Log::error('Report export failed', [
                'report_title' => $report->title,
                'format' => $request->format,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ExportException(
                "Failed to export report: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Get available export formats
     */
    public function getAvailableFormats(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Register export drivers
     */
    private function registerDrivers(): void
    {
        $this->drivers = [
            'pdf' => new \App\Domain\Report\Drivers\PdfExportDriver(),
            'excel' => new \App\Domain\Report\Drivers\ExcelExportDriver(),
            'csv' => new \App\Domain\Report\Drivers\CsvExportDriver(),
            'html' => new \App\Domain\Report\Drivers\HtmlExportDriver(),
        ];
    }

    /**
     * Get export driver for format
     */
    private function getDriver(string $format): ExportDriverInterface
    {
        if (!isset($this->drivers[$format])) {
            throw new ExportException("Unsupported export format: {$format}");
        }

        return $this->drivers[$format];
    }

    /**
     * Validate export request
     */
    private function validateExportRequest(ExportRequestDTO $request): void
    {
        if (!in_array($request->format, $this->getAvailableFormats())) {
            throw new ExportException("Invalid export format: {$request->format}");
        }

        // Validate file size limits
        if ($request->estimatedSize > config('reports.max_export_size', 50 * 1024 * 1024)) {
            throw new ExportException('Report too large for export. Please reduce date range or apply filters.');
        }

        // Validate user permissions
        if (!auth()->user()->can('export-reports')) {
            throw new ExportException('Insufficient permissions to export reports');
        }
    }
}
