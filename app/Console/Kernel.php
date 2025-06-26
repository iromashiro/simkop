<?php
// app/Console/Kernel.php - ADD SCHEDULING
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\CleanupExpiredSessionsJob;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // ✅ FIXED: Schedule session cleanup job
        $schedule->job(new CleanupExpiredSessionsJob(120)) // 2 hours timeout
            ->hourly()
            ->name('cleanup-expired-sessions')
            ->withoutOverlapping()
            ->runInBackground()
            ->onSuccess(function () {
                \Log::info('Session cleanup job completed successfully');
            })
            ->onFailure(function () {
                \Log::error('Session cleanup job failed');
            });

        // ✅ Additional cleanup for very old sessions (7 days)
        $schedule->job(new CleanupExpiredSessionsJob(10080)) // 7 days
            ->daily()
            ->at('02:00')
            ->name('cleanup-old-sessions')
            ->withoutOverlapping();

        // ✅ Existing scheduled commands
        $schedule->command('hermes:backup-database')
            ->daily()
            ->at('01:00')
            ->name('daily-database-backup')
            ->withoutOverlapping();

        $schedule->command('hermes:generate-reports')
            ->monthly()
            ->name('monthly-reports-generation')
            ->withoutOverlapping();

        $schedule->command('hermes:close-fiscal-period')
            ->yearly()
            ->at('23:59')
            ->name('yearly-fiscal-period-closing')
            ->withoutOverlapping();
    }

    protected $commands = [
        \App\Console\Commands\BackupDatabaseCommand::class,
        \App\Console\Commands\GenerateReportsCommand::class,
        \App\Console\Commands\CloseFiscalPeriodCommand::class,
    ];
}
