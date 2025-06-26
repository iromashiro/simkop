<?php
// app/Jobs/CleanupExpiredSessionsJob.php
namespace App\Jobs;

use App\Domain\Auth\Services\SessionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredSessionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes timeout
    public int $tries = 3;

    public function __construct(
        private readonly int $timeoutMinutes = 120
    ) {}

    /**
     * ✅ FIXED: Execute the session cleanup job
     */
    public function handle(SessionService $sessionService): void
    {
        $startTime = microtime(true);

        try {
            Log::info('Starting expired sessions cleanup', [
                'timeout_minutes' => $this->timeoutMinutes,
                'job_id' => $this->job->getJobId(),
            ]);

            $cleanedCount = $sessionService->cleanupExpiredSessions($this->timeoutMinutes);

            // Also cleanup old login attempts (older than 30 days)
            $cleanedAttempts = $sessionService->cleanupOldLoginAttempts(30);

            $executionTime = microtime(true) - $startTime;

            Log::info('Expired sessions cleanup completed successfully', [
                'cleaned_sessions' => $cleanedCount,
                'cleaned_login_attempts' => $cleanedAttempts,
                'execution_time' => round($executionTime, 2),
                'job_id' => $this->job->getJobId(),
            ]);

            // Send notification if many sessions were cleaned
            if ($cleanedCount > 100) {
                $this->notifyAdminsOfMassCleanup($cleanedCount);
            }
        } catch (\Exception $e) {
            Log::error('Session cleanup job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Session cleanup job failed permanently', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'job_id' => $this->job->getJobId(),
        ]);
    }

    /**
     * Notify administrators of mass session cleanup
     */
    private function notifyAdminsOfMassCleanup(int $cleanedCount): void
    {
        // This would integrate with the notification system
        Log::warning('Mass session cleanup detected', [
            'cleaned_sessions' => $cleanedCount,
            'recommendation' => 'Review session timeout settings',
        ]);
    }
}
