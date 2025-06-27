<?php

namespace App\Providers;

use App\Listeners\NotificationEventSubscriber;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Traditional event-listener mappings can be added here
        // Example:
        // 'App\Events\UserRegistered' => [
        //     'App\Listeners\SendWelcomeEmail',
        // ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        NotificationEventSubscriber::class, // ✅ Register notification subscriber
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        // ✅ Register custom events for financial reporting
        Event::listen('financial.report.submitted', function ($event) {
            \Log::info('Financial report submitted event fired', [
                'cooperative_id' => $event->cooperativeId ?? null,
                'report_type' => $event->reportType ?? null,
                'reporting_year' => $event->reportingYear ?? null,
            ]);
        });

        Event::listen('financial.report.approved', function ($event) {
            \Log::info('Financial report approved event fired', [
                'cooperative_id' => $event->cooperativeId ?? null,
                'report_type' => $event->reportType ?? null,
                'reporting_year' => $event->reportingYear ?? null,
            ]);
        });

        Event::listen('financial.report.rejected', function ($event) {
            \Log::info('Financial report rejected event fired', [
                'cooperative_id' => $event->cooperativeId ?? null,
                'report_type' => $event->reportType ?? null,
                'reporting_year' => $event->reportingYear ?? null,
                'reason' => $event->rejectionReason ?? null,
            ]);
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // We manually register events for better control
    }

    /**
     * Get the listener directories that should be used to discover events.
     */
    protected function discoverEventsWithin(): array
    {
        return [
            $this->app->path('Listeners'),
        ];
    }
}
