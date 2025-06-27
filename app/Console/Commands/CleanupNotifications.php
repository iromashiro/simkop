<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class CleanupNotifications extends Command
{
    protected $signature = 'notifications:cleanup {--days=30 : Number of days to keep read notifications}';
    protected $description = 'Clean up old read notifications';

    public function __construct(
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning up notifications older than {$days} days...");

        $deletedCount = $this->notificationService->cleanupOldNotifications($days);

        $this->info("Successfully deleted {$deletedCount} old notifications.");

        return Command::SUCCESS;
    }
}
