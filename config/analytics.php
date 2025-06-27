<?php
// config/analytics.php - Enhanced Configuration
return [
    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration - Enhanced Version
    |--------------------------------------------------------------------------
    | Configuration for HERMES Analytics System based on Mikail's review
    */

    'max_date_range_days' => env('ANALYTICS_MAX_DATE_RANGE_DAYS', 365),
    'min_date_range_days' => env('ANALYTICS_MIN_DATE_RANGE_DAYS', 1),
    'max_processing_time' => env('ANALYTICS_MAX_PROCESSING_TIME', 60),

    'valid_periods' => ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'],

    'valid_widgets' => [
        'financial_overview',
        'member_growth',
        'savings_trends',
        'loan_portfolio',
        'profitability',
        'risk_metrics'
    ],

    'default_widgets' => [
        'financial_overview',
        'member_growth',
        'savings_trends',
        'loan_portfolio'
    ],

    'default_periods' => [
        'daily' => 30,
        'weekly' => 12,
        'monthly' => 12,
        'quarterly' => 4,
        'yearly' => 3,
    ],

    'validate_cooperative_exists' => env('ANALYTICS_VALIDATE_COOPERATIVE', true),

    'cache' => [
        'enabled' => env('ANALYTICS_CACHE_ENABLED', true),
        'default_ttl' => env('ANALYTICS_CACHE_TTL', 1800),
        'savings_trends_ttl' => env('ANALYTICS_SAVINGS_CACHE_TTL', 1800),
        'financial_overview_ttl' => env('ANALYTICS_FINANCIAL_CACHE_TTL', 3600),
        'loan_portfolio_ttl' => env('ANALYTICS_LOAN_CACHE_TTL', 1800),
        'profitability_ttl' => env('ANALYTICS_PROFITABILITY_CACHE_TTL', 3600),
        'risk_metrics_ttl' => env('ANALYTICS_RISK_CACHE_TTL', 3600),
    ],

    'savings' => [
        'max_top_savers' => env('ANALYTICS_MAX_TOP_SAVERS', 50),
        'min_balance' => env('ANALYTICS_MIN_BALANCE', 0),
    ],

    'loans' => [
        'max_top_borrowers' => env('ANALYTICS_MAX_TOP_BORROWERS', 50),
        'par_thresholds' => [
            'warning' => 5,  // 5%
            'danger' => 10,  // 10%
            'critical' => 15 // 15%
        ]
    ],

    'performance' => [
        'enable_query_logging' => env('ANALYTICS_QUERY_LOGGING', false),
        'slow_query_threshold' => env('ANALYTICS_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'memory_limit' => env('ANALYTICS_MEMORY_LIMIT', '256M'),
    ],

    'security' => [
        'rate_limit' => env('ANALYTICS_RATE_LIMIT', 60), // requests per minute
        'enable_audit_log' => env('ANALYTICS_AUDIT_LOG', true),
        'sanitize_inputs' => env('ANALYTICS_SANITIZE_INPUTS', true),
    ],

    'export' => [
        'max_records' => env('ANALYTICS_EXPORT_MAX_RECORDS', 10000),
        'allowed_formats' => ['json', 'csv', 'excel', 'pdf'],
        'temp_directory' => storage_path('app/temp/analytics'),
    ],
];
