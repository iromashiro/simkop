<?php
// app/Domain/Report/Services/QueuedExportService.php
namespace App\Domain\Report\Services;

use App\Domain\Report\DTOs\ReportResultDTO;
use App\Domain\Report\DTOs\ExportRequestDTO;
use App\Domain\Report\Jobs\ExportReportJob;
use App\Domain\Report\Models\ExportRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;

/**
 * Queued Export Service for large file processing
 */
class QueuedExportService
{
    private const LARGE_FILE_THRESHOLD = 10 * 1024 * 1024; // 10MB
    private const ESTIMATED_ROWS_THRESHOLD = 5000;

    /**
     * Determine if export should be queued and handle accordingly
     */
    public function handleExport(ReportResultDTO $report, ExportRequestDTO $request): Response
    {
        if ($this->shouldQueue($report, $request)) {
            return $this->queueExport($report, $request);
        }

        // Use regular export service for smaller files
        $exportService = app(\App\Domain\Report\Services\ReportExportService::class);
        return $exportService->export($report, $request);
    }

    /**
     * Determine if export should be queued
     */
    private function shouldQueue(ReportResultDTO $report, ExportRequestDTO $request): bool
    {
        // Queue if estimated size is large
        if ($request->estimatedSize > self::LARGE_FILE_THRESHOLD) {
            return true;
        }

        // Queue if estimated rows are many
        $estimatedRows = $this->estimateRows($report);
        if ($estimatedRows > self::ESTIMATED_ROWS_THRESHOLD) {
            return true;
        }

        // Queue if format is Excel and data is complex
        if ($request->format === 'excel' && $this->isComplexData($report)) {
            return true;
        }

        return false;
    }

    /**
     * Queue export for background processing
     */
    private function queueExport(ReportResultDTO $report, ExportRequestDTO $request): Response
    {
        try {
            // Create export request record
            $exportRequest = ExportRequest::create([
                'user_id' => auth()->id(),
                'cooperative_id' => $report->cooperativeId,
                'report_title' => $report->title,
                'format' => $request->format,
                'status' => 'queued',
                'estimated_size' => $request->estimatedSize,
                'options' => $request->options,
                'filename' => $request->filename,
            ]);

            // Dispatch job
            $job = new ExportReportJob($report, $request, $exportRequest, auth()->user());
            dispatch($job)->onQueue('exports');

            Log::info('Export queued successfully', [
                'export_request_id' => $exportRequest->id,
                'user_id' => auth()->id(),
                'report_title' => $report->title,
                'format' => $request->format,
                'estimated_size' => $request->estimatedSize,
            ]);

            return response()->json([
                'status' => 'queued',
                'export_id' => $exportRequest->id,
                'message' => 'Export has been queued for processing. You will be notified when ready.',
                'estimated_time' => $this->getEstimatedTime($request),
                'check_url' => route('exports.status', $exportRequest->id),
            ], 202); // HTTP 202 Accepted

        } catch (\Exception $e) {
            Log::error('Failed to queue export', [
                'error' => $e->getMessage(),
                'report_title' => $report->title,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to queue export. Please try again.',
            ], 500);
        }
    }

    /**
     * Estimate number of rows in report
     */
    private function estimateRows(ReportResultDTO $report): int
    {
        $data = $report->data;

        if (isset($data['members'])) {
            return count($data['members']);
        } elseif (isset($data['assets'])) {
            return $this->countAccountRows($data['assets']) +
                $this->countAccountRows($data['liabilities']) +
                $this->countAccountRows($data['equity']);
        }

        return is_array($data) ? count($data) : 0;
    }

    /**
     * Count rows in account hierarchy
     */
    private function countAccountRows(array $accounts): int
    {
        $count = count($accounts);

        foreach ($accounts as $account) {
            if (!empty($account['children'])) {
                $count += $this->countAccountRows($account['children']);
            }
        }

        return $count;
    }

    /**
     * Check if data is complex (nested structures)
     */
    private function isComplexData(ReportResultDTO $report): bool
    {
        $data = $report->data;

        // Check for nested account structures
        if (isset($data['assets'])) {
            foreach ($data['assets'] as $account) {
                if (!empty($account['children'])) {
                    return true;
                }
            }
        }

        // Check for complex member data
        if (isset($data['members'])) {
            foreach ($data['members'] as $member) {
                if (isset($member['savings']) && is_array($member['savings'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get estimated processing time
     */
    private function getEstimatedTime(ExportRequestDTO $request): string
    {
        $baseTime = match ($request->format) {
            'pdf' => 2,      // 2 minutes base
            'excel' => 5,    // 5 minutes base
            'csv' => 1,      // 1 minute base
            'html' => 1,     // 1 minute base
            default => 3
        };

        // Adjust based on estimated size
        $sizeMultiplier = max(1, $request->estimatedSize / (5 * 1024 * 1024)); // 5MB baseline
        $estimatedMinutes = ceil($baseTime * $sizeMultiplier);

        return "{$estimatedMinutes}-" . ($estimatedMinutes + 2) . " minutes";
    }
}
