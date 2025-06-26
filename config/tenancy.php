<?php
// config/tenancy.php
return [
    /*
     * Tenant model configuration for Laravel 11
     */
    'tenant_model' => App\Domain\Cooperative\Models\Cooperative::class,

    /*
     * Tenant identification
     */
    'tenant_key' => 'cooperative_id',
    'tenant_column' => 'cooperative_id',

    /*
     * Central domains (non-tenant)
     */
    'central_domains' => [
        'localhost',
        '127.0.0.1',
        env('APP_DOMAIN', 'hermes.local'),
    ],

    /*
     * Tenant resolution
     */
    'tenant_resolution' => [
        'header' => 'X-Tenant-ID',
        'session_key' => 'tenant_id',
        'cache_key' => 'tenant_cache',
    ],

    /*
     * Database configuration
     */
    'database' => [
        'tenant_aware_models' => [
            App\Domain\Cooperative\Models\Cooperative::class,
            App\Domain\User\Models\User::class,
            App\Domain\Member\Models\Member::class,
            App\Domain\Accounting\Models\Account::class,
            App\Domain\Accounting\Models\JournalEntry::class,
            App\Domain\Accounting\Models\JournalEntryLine::class,
            App\Domain\Savings\Models\SavingsAccount::class,
            App\Domain\Savings\Models\SavingsTransaction::class,
            App\Domain\Loan\Models\LoanAccount::class,
            App\Domain\Loan\Models\LoanPayment::class,
            App\Domain\Notification\Models\Notification::class,
            App\Domain\System\Models\ActivityLog::class,
        ],

        'tenant_columns' => [
            'cooperative_id',
        ],

        'exclude_from_tenant_scope' => [
            'permissions',
            'password_resets',
            'personal_access_tokens',
            'failed_jobs',
            'migrations',
        ],
    ],

    /*
     * Cache configuration
     */
    'cache' => [
        'tenant_cache_prefix' => 'tenant',
        'tenant_cache_ttl' => 3600, // 1 hour
        'clear_cache_on_tenant_switch' => true,
    ],

    /*
     * Security configuration
     */
    'security' => [
        'enforce_tenant_isolation' => true,
        'log_tenant_access' => true,
        'tenant_access_log_channel' => 'tenant',
        'block_cross_tenant_access' => true,
    ],

    /*
     * Performance configuration
     */
    'performance' => [
        'eager_load_tenant' => true,
        'cache_tenant_data' => true,
        'optimize_queries' => true,
    ],
];
