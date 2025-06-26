<?php
// app/Domain/Report/Services/ExportCleanupService.php
namespace App\Domain\Report\Services;

use App\Domain\Report\Models\ExportRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Service for cleaning up old export files and records
 */
class ExportCleanupService
{
    private const DEFAULT_RETENTION_DAYS = 7;
    private const FAILED_RETENTION_DAYS = 3;

    /**
     * Clean up old exports
     */
    public function cleanupOldExports(int $retentionDays = self::DEFAULT_RETENTION_DAYS): array
    {
        $cutoffDate = now()->subDays($retentionDays);
        $stats = [
            'files_deleted' => 0,
            'records_deleted' => 0,
            'space_freed' => 0,
            'errors' => [],
        ];

        try {
            Log::info('Starting export cleanup', [
                'cutoff_date' => $cutoffDate->toDateString(),
                'retention_days' => $retentionDays,
            ]);

            // Get old export requests
            $oldExports = ExportRequest::where('created_at', '<', $cutoffDate)
                ->whereNotNull('file_path')
                ->get();

            foreach ($oldExports as $export) {
                try {
                    // Delete file if exists
                    if ($export->file_path && Storage::disk('local')->exists($export->file_path)) {
                        $fileSize = Storage::disk('local')->size($export->file_path);
                        Storage::disk('local')->delete($export->file_path);

                        $stats['files_deleted']++;
                        $stats['space_freed'] += $fileSize;
                    }

                    // Delete record
                    $export->delete();
                    $stats['records_deleted']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = [
                        'export_id' => $export->id,
                        'error' => $e->getMessage(),
                    ];

                    Log::warning('Failed to cleanup export', [
                        'export_id' => $export->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Clean up failed exports separately (shorter retention)
            $this->cleanupFailedExports();

            Log::info('Export cleanup completed', $stats);

            return $stats;
        } catch (\Exception $e) {
            Log::error('Export cleanup failed', [
                'error' => $e->getMessage(),
            ]);

            $stats['errors'][] = ['general' => $e->getMessage()];
            return $stats;
        }
    }

    /**
     * Clean up failed exports with shorter retention
     */
    public function cleanupFailedExports(): array
    {
        $cutoffDate = now()->subDays(self::FAILED_RETENTION_DAYS);
        $stats = [
            'failed_exports_deleted' => 0,
            'errors' => [],
        ];

        try {
            $failedExports = ExportRequest::where('status', ExportRequest::STATUS_FAILED)
                ->where('created_at', '<', $cutoffDate)
                ->get();

            foreach ($failedExports as $export) {
                try {
                    // Delete file if exists
                    if ($export->file_path && Storage::disk('local')->exists($export->file_path)) {
                        Storage::disk('local')->delete($export->file_path);
                    }

                    $export->delete();
                    $stats['failed_exports_deleted']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = [
                        'export_id' => $export->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            Log::info('Failed exports cleanup completed', $stats);
        } catch (\Exception $e) {
            Log::error('Failed exports cleanup error', [
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    /**
     * Clean up expired exports
     */
    public function cleanupExpiredExports(): array
    {
        $stats = [
            'expired_exports_deleted' => 0,
            'errors' => [],
        ];

        try {
            $expiredExports = ExportRequest::where('expires_at', '<', now())
                ->whereNotIn('status', [ExportRequest::STATUS_PROCESSING])
                ->get();

            foreach ($expiredExports as $export) {
                try {
                    // Delete file if exists
                    if ($export->file_path && Storage::disk('local')->exists($export->file_path)) {
                        Storage::disk('local')->delete($export->file_path);
                    }

                    // Update status instead of deleting for audit trail
                    $export->update(['status' => ExportRequest::STATUS_EXPIRED]);
                    $stats['expired_exports_deleted']++;
                } catch (\Exception $e) {
                    $stats['errors'][] = [
                        'export_id' => $export->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            Log::info('Expired exports cleanup completed', $stats);
        } catch (\Exception $e) {
            Log::error('Expired exports cleanup error', [
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    /**
     * Get cleanup statistics
     */
    public function getCleanupStats(): array
    {
        $totalExports = ExportRequest::count();
        $completedExports = ExportRequest::where('status', ExportRequest::STATUS_COMPLETED)->count();
        $failedExports = ExportRequest::where('status', ExportRequest::STATUS_FAILED)->count();
        $expiredExports = ExportRequest::where('status', ExportRequest::STATUS_EXPIRED)->count();

        // Calculate total storage used
        $totalStorage = ExportRequest::whereNotNull('file_size')->sum('file_size');

        // Get old exports count
        $oldExportsCount = ExportRequest::where('created_at', '<', now()->subDays(self::DEFAULT_RETENTION_DAYS))->count();

        return [
            'total_exports' => $totalExports,
            'completed_exports' => $completedExports,
            'failed_exports' => $failedExports,
            'expired_exports' => $expiredExports,
            'old_exports_count' => $oldExportsCount,
            'total_storage_bytes' => $totalStorage,
            'total_storage_formatted' => $this->formatBytes($totalStorage),
            'cleanup_recommendations' => $this->getCleanupRecommendations($oldExportsCount, $failedExports),
        ];
    }

    /**
     * Get cleanup recommendations
     */
    private function getCleanupRecommendations(int $oldExportsCount, int $failedExportsCount): array
    {
        $recommendations = [];

        if ($oldExportsCount > 100) {
            $recommendations[] = [
                'type' => 'old_exports',
                'message' => "You have {$oldExportsCount} old exports that can be cleaned up",
                'action' => 'Run cleanup to free storage space',
            ];
        }

        if ($failedExportsCount > 50) {
            $recommendations[] = [
                'type' => 'failed_exports',
                'message' => "You have {$failedExportsCount} failed exports",
                'action' => 'Consider investigating export failures and cleaning up failed records',
            ];
        }

        return $recommendations;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Schedule automatic cleanup
     */
    public function scheduleAutomaticCleanup(): void
    {
        // This would be called from a scheduled command
        Log::info('Running scheduled export cleanup');

        // Clean up old exports
        $this->cleanupOldExports();

        // Clean up expired exports
        $this->cleanupExpiredExports();

        // Clean up failed exports
        $this->cleanupFailedExports();
    }
}
