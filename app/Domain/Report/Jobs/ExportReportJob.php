<?php
// app/Domain/Report/Jobs/ExportReportJob.php
namespace App\Domain\Report\Jobs;

use App\Domain\Report\DTOs\ReportResultDTO;
use App\Domain\Report\DTOs\ExportRequestDTO;
use App\Domain\Report\Models\ExportRequest;
use App\Domain\Report\Services\ReportExportService;
use App\Domain\User\Models\User;
use App\Notifications\ExportCompletedNotification;
use App\Notifications\ExportFailedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Background job for processing large export requests
 */
class ExportReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes
    public int $tries = 3;
    public int $maxExceptions = 1;

    public function __construct(
        private readonly ReportResultDTO $report,
        private readonly ExportRequestDTO $exportRequest,
        private readonly ExportRequest $exportRecord,
        private readonly User $user
    ) {
        $this->onQueue('exports');
    }

    /**
     * Execute the job
     */
    public function handle(ReportExportService $exportService): void
    {
        $startTime = microtime(true);

        try {
            Log::info('Starting background export', [
                'export_id' => $this->exportRecord->id,
                'user_id' => $this->user->id,
                'format' => $this->exportRequest->format,
                'report_title' => $this->report->title,
            ]);

            // Update status to processing
            $this->exportRecord->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Set memory limit for large exports
            ini_set('memory_limit', '1G');
            set_time_limit(1800); // 30 minutes

            // Generate export
            $exportResult = $exportService->export($this->report, $this->exportRequest);

            // Store file
            $filename = $this->generateUniqueFilename();
            $filePath = "exports/{$this->user->id}/{$filename}";

            Storage::disk('local')->put($filePath, $exportResult->getContent());

            $executionTime = microtime(true) - $startTime;

            // Update export record
            $this->exportRecord->update([
                'status' => 'completed',
                'file_path' => $filePath,
                'file_size' => $exportResult->getSize(),
                'completed_at' => now(),
                'execution_time' => $executionTime,
            ]);

            // Notify user
            $this->user->notify(new ExportCompletedNotification($this->exportRecord));

            Log::info('Background export completed', [
                'export_id' => $this->exportRecord->id,
                'execution_time' => $executionTime,
                'file_size' => $exportResult->getSize(),
                'file_path' => $filePath,
            ]);
        } catch (\Exception $e) {
            $this->handleFailure($e, $startTime);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Export job failed permanently', [
            'export_id' => $this->exportRecord->id,
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Update export record
        $this->exportRecord->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'failed_at' => now(),
        ]);

        // Notify user of failure
        $this->user->notify(new ExportFailedNotification($this->exportRecord, $exception->getMessage()));
    }

    /**
     * Handle export failure during processing
     */
    private function handleFailure(\Throwable $exception, float $startTime): void
    {
        $executionTime = microtime(true) - $startTime;

        Log::error('Export processing failed', [
            'export_id' => $this->exportRecord->id,
            'error' => $exception->getMessage(),
            'execution_time' => $executionTime,
            'attempt' => $this->attempts(),
        ]);

        // Update export record
        $this->exportRecord->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'failed_at' => now(),
            'execution_time' => $executionTime,
        ]);

        // Re-throw to trigger retry mechanism
        throw $exception;
    }

    /**
     * Generate unique filename for export
     */
    private function generateUniqueFilename(): string
    {
        $title = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->report->title);
        $timestamp = now()->format('Y_m_d_H_i_s');
        $extension = $this->getFileExtension();

        return "{$title}_{$timestamp}_{$this->exportRecord->id}.{$extension}";
    }

    /**
     * Get file extension for format
     */
    private function getFileExtension(): string
    {
        return match ($this->exportRequest->format) {
            'pdf' => 'pdf',
            'excel' => 'xlsx',
            'csv' => 'csv',
            'html' => 'html',
            default => 'txt'
        };
    }

    /**
     * Get job tags for monitoring
     */
    public function tags(): array
    {
        return [
            'export',
            "user:{$this->user->id}",
            "cooperative:{$this->report->cooperativeId}",
            "format:{$this->exportRequest->format}",
        ];
    }
}
