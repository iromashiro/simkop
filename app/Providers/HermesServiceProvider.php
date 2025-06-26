<?php
// app/Providers/HermesServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use App\Infrastructure\Performance\CacheManager;
use App\Infrastructure\Performance\QueryOptimizer;
use App\Infrastructure\Notification\NotificationManager;
use App\Infrastructure\Analytics\AnalyticsEngine;
use App\Infrastructure\Monitoring\DatabaseMonitor;

class HermesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register infrastructure services
        $this->app->singleton(CacheManager::class);
        $this->app->singleton(QueryOptimizer::class);
        $this->app->singleton(NotificationManager::class);
        $this->app->singleton(AnalyticsEngine::class);
        $this->app->singleton(DatabaseMonitor::class);

        // Register domain services
        $this->registerDomainServices();

        // Register configuration
        $this->mergeConfigFrom(__DIR__ . '/../../config/tenancy.php', 'tenancy');
        $this->mergeConfigFrom(__DIR__ . '/../../config/reporting.php', 'reporting');
        $this->mergeConfigFrom(__DIR__ . '/../../config/security.php', 'security');
        $this->mergeConfigFrom(__DIR__ . '/../../config/notification.php', 'notification');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register middleware
        $this->registerMiddleware();

        // Register gates and policies
        $this->registerGatesAndPolicies();

        // Register Blade directives
        $this->registerBladeDirectives();

        // Register view composers
        $this->registerViewComposers();

        // Register event listeners
        $this->registerEventListeners();

        // Publish configuration files
        $this->publishes([
            __DIR__ . '/../../config/tenancy.php' => config_path('tenancy.php'),
            __DIR__ . '/../../config/reporting.php' => config_path('reporting.php'),
            __DIR__ . '/../../config/security.php' => config_path('security.php'),
            __DIR__ . '/../../config/notification.php' => config_path('notification.php'),
        ], 'hermes-config');
    }

    /**
     * Register domain services
     */
    private function registerDomainServices(): void
    {
        // Cooperative Services
        $this->app->bind(
            \App\Domain\Cooperative\Contracts\CooperativeRepositoryInterface::class,
            \App\Domain\Cooperative\Repositories\CooperativeRepository::class
        );

        $this->app->bind(
            \App\Domain\Cooperative\Services\CooperativeService::class
        );

        // User Services
        $this->app->bind(
            \App\Domain\User\Contracts\UserRepositoryInterface::class,
            \App\Domain\User\Repositories\UserRepository::class
        );

        $this->app->bind(
            \App\Domain\User\Services\UserService::class
        );

        // Member Services
        $this->app->bind(
            \App\Domain\Member\Contracts\MemberRepositoryInterface::class,
            \App\Domain\Member\Repositories\MemberRepository::class
        );

        $this->app->bind(
            \App\Domain\Member\Services\MemberService::class
        );

        // Financial Services
        $this->app->bind(
            \App\Domain\Accounting\Contracts\AccountRepositoryInterface::class,
            \App\Domain\Accounting\Repositories\AccountRepository::class
        );

        $this->app->bind(
            \App\Domain\Accounting\Services\AccountService::class
        );

        $this->app->bind(
            \App\Domain\Accounting\Services\JournalEntryService::class
        );

        $this->app->bind(
            \App\Domain\Accounting\Services\FiscalPeriodService::class
        );

        // Savings Services
        $this->app->bind(
            \App\Domain\Savings\Contracts\SavingsRepositoryInterface::class,
            \App\Domain\Savings\Repositories\SavingsRepository::class
        );

        $this->app->bind(
            \App\Domain\Savings\Services\SavingsService::class
        );

        // Loan Services
        $this->app->bind(
            \App\Domain\Loan\Contracts\LoanRepositoryInterface::class,
            \App\Domain\Loan\Repositories\LoanRepository::class
        );

        $this->app->bind(
            \App\Domain\Loan\Services\LoanService::class
        );

        // Reporting Services
        $this->app->bind(
            \App\Domain\Reporting\Services\ReportService::class
        );

        // System Services
        $this->app->bind(
            \App\Domain\System\Services\SettingsService::class
        );

        $this->app->bind(
            \App\Domain\System\Services\ActivityLogService::class
        );
    }

    /**
     * Register middleware
     */
    private function registerMiddleware(): void
    {
        $router = $this->app['router'];

        // Register middleware aliases
        $router->aliasMiddleware('cooperative.access', \App\Http\Middleware\CheckCooperativeAccess::class);
        $router->aliasMiddleware('audit.log', \App\Http\Middleware\AuditLogMiddleware::class);
        $router->aliasMiddleware('role', \App\Http\Middleware\RoleMiddleware::class);
        $router->aliasMiddleware('permission', \App\Http\Middleware\PermissionMiddleware::class);
        $router->aliasMiddleware('security.headers', \App\Http\Middleware\SecurityHeadersMiddleware::class);

        // Register middleware groups
        $router->middlewareGroup('tenant', [
            'cooperative.access',
            'audit.log',
        ]);

        $router->middlewareGroup('api.secure', [
            'auth:sanctum',
            'cooperative.access',
            'audit.log',
            'throttle:api',
        ]);
    }

    /**
     * Register gates and policies
     */
    private function registerGatesAndPolicies(): void
    {
        // Register gates for HERMES permissions
        Gate::define('manage_cooperative', function ($user) {
            return $user->can('manage_cooperative_settings');
        });

        Gate::define('view_financial_reports', function ($user) {
            return $user->can('view_reports') || $user->can('generate_financial_reports');
        });

        Gate::define('manage_members', function ($user) {
            return $user->can('create_members') || $user->can('edit_members');
        });

        Gate::define('process_transactions', function ($user) {
            return $user->can('process_deposits') || $user->can('process_withdrawals') || $user->can('process_payments');
        });

        Gate::define('system_administration', function ($user) {
            return $user->can('manage_settings') || $user->can('view_audit_logs');
        });
    }

    /**
     * Register Blade directives
     */
    private function registerBladeDirectives(): void
    {
        // Cooperative context directive
        Blade::directive('cooperative', function ($expression) {
            return "<?php echo auth()->user()?->cooperative?->{$expression} ?? 'N/A'; ?>";
        });

        // Permission check directive
        Blade::directive('canany', function ($expression) {
            return "<?php if(auth()->check() && auth()->user()->canAny({$expression})): ?>";
        });

        Blade::directive('endcanany', function () {
            return '<?php endif; ?>';
        });

        // Currency formatting directive
        Blade::directive('currency', function ($expression) {
            return "<?php echo number_format({$expression}, 2, '.', ','); ?>";
        });

        // Date formatting directive
        Blade::directive('dateformat', function ($expression) {
            return "<?php echo {$expression}?->format('d/m/Y') ?? '-'; ?>";
        });

        // Status badge directive
        Blade::directive('statusbadge', function ($expression) {
            return "<?php echo view('components.status-badge', ['status' => {$expression}]); ?>";
        });
    }

    /**
     * Register view composers
     */
    private function registerViewComposers(): void
    {
        // Global navigation data
        View::composer('layouts.*', function ($view) {
            $user = auth()->user();
            if ($user && $user->cooperative) {
                $view->with([
                    'currentCooperative' => $user->cooperative,
                    'userPermissions' => $user->getAllPermissions()->pluck('name'),
                    'unreadNotifications' => $user->unreadNotifications()->count(),
                ]);
            }
        });

        // Dashboard data
        View::composer('dashboard.*', function ($view) {
            $user = auth()->user();
            if ($user && $user->cooperative_id) {
                $cacheManager = app(CacheManager::class);

                $dashboardData = $cacheManager->remember(
                    'dashboard_widgets',
                    ['user_id' => $user->id],
                    function () use ($user) {
                        return [
                            'total_members' => app(\App\Domain\Member\Services\MemberService::class)
                                ->getActiveCount($user->cooperative_id),
                            'total_savings' => app(\App\Domain\Savings\Services\SavingsService::class)
                                ->getTotalSavings($user->cooperative_id),
                            'total_loans' => app(\App\Domain\Loan\Services\LoanService::class)
                                ->getTotalOutstanding($user->cooperative_id),
                        ];
                    }
                );

                $view->with('dashboardData', $dashboardData);
            }
        });
    }

    /**
     * Register event listeners
     */
    private function registerEventListeners(): void
    {
        // Listen for model events to clear cache
        $this->app['events']->listen('eloquent.saved: *', function ($event, $models) {
            foreach ($models as $model) {
                if (method_exists($model, 'cooperative_id') && $model->cooperative_id) {
                    CacheManager::invalidateCooperative($model->cooperative_id);
                }
            }
        });

        // Listen for user login to warm cache
        $this->app['events']->listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            if ($event->user->cooperative_id) {
                dispatch(function () use ($event) {
                    app(\App\Infrastructure\Performance\CacheWarmer::class)
                        ->warmCooperativeCache($event->user->cooperative_id);
                })->afterResponse();
            }
        });
    }
}
