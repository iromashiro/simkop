<?php

namespace App\Listeners;

use App\Services\NotificationService;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class NotificationEventSubscriber
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        // Financial report events
        $events->listen(
            'financial.report.submitted',
            [NotificationEventSubscriber::class, 'handleReportSubmitted']
        );

        $events->listen(
            'financial.report.approved',
            [NotificationEventSubscriber::class, 'handleReportApproved']
        );

        $events->listen(
            'financial.report.rejected',
            [NotificationEventSubscriber::class, 'handleReportRejected']
        );
    }

    /**
     * Handle report submitted event
     */
    public function handleReportSubmitted($event): void
    {
        try {
            $this->notificationService->reportSubmitted(
                $event->cooperativeId,
                $event->reportType,
                $event->reportingYear
            );

            Log::info('Report submitted notification sent', [
                'cooperative_id' => $event->cooperativeId,
                'report_type' => $event->reportType,
                'reporting_year' => $event->reportingYear
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send report submitted notification', [
                'cooperative_id' => $event->cooperativeId ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle report approved event
     */
    public function handleReportApproved($event): void
    {
        try {
            $this->notificationService->reportApproved(
                $event->cooperativeId,
                $event->reportType,
                $event->reportingYear
            );

            Log::info('Report approved notification sent', [
                'cooperative_id' => $event->cooperativeId,
                'report_type' => $event->reportType,
                'reporting_year' => $event->reportingYear
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send report approved notification', [
                'cooperative_id' => $event->cooperativeId ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle report rejected event
     */
    public function handleReportRejected($event): void
    {
        try {
            $this->notificationService->reportRejected(
                $event->cooperativeId,
                $event->reportType,
                $event->reportingYear,
                $event->rejectionReason
            );

            Log::info('Report rejected notification sent', [
                'cooperative_id' => $event->cooperativeId,
                'report_type' => $event->reportType,
                'reporting_year' => $event->reportingYear,
                'reason' => $event->rejectionReason
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send report rejected notification', [
                'cooperative_id' => $event->cooperativeId ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }
}
