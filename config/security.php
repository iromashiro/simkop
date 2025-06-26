<?php
// config/security.php
return [
    /*
     * Authentication configuration
     */
    'authentication' => [
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'password_reset_timeout' => 3600, // 1 hour
        'session_timeout' => 7200, // 2 hours
        'require_email_verification' => true,
        'two_factor_enabled' => false,
    ],

    /*
     * Password policy
     */
    'password_policy' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => false,
        'prevent_reuse' => 5, // Last 5 passwords
        'expiry_days' => 90,
        'force_change_on_first_login' => true,
    ],

    /*
     * Rate limiting configuration
     */
    'rate_limiting' => [
        'api' => [
            'requests_per_minute' => 60,
            'burst_limit' => 100,
        ],
        'login' => [
            'attempts_per_minute' => 5,
            'lockout_duration' => 900,
        ],
        'password_reset' => [
            'attempts_per_hour' => 3,
        ],
    ],

    /*
     * CORS configuration for Laravel 11
     */
    'cors' => [
        'paths' => ['api/*', 'sanctum/csrf-cookie'],
        'allowed_methods' => ['*'],
        'allowed_origins' => [
            env('FRONTEND_URL', 'http://localhost:3000'),
            env('APP_URL', 'http://localhost'),
        ],
        'allowed_origins_patterns' => [],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 0,
        'supports_credentials' => true,
    ],

    /*
     * Content Security Policy
     */
    'csp' => [
        'enabled' => true,
        'report_only' => false,
        'directives' => [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline' 'unsafe-eval'",
            'style-src' => "'self' 'unsafe-inline'",
            'img-src' => "'self' data: https:",
            'font-src' => "'self'",
            'connect-src' => "'self'",
            'frame-src' => "'none'",
            'object-src' => "'none'",
        ],
    ],

    /*
     * Security headers
     */
    'headers' => [
        'x-frame-options' => 'DENY',
        'x-content-type-options' => 'nosniff',
        'x-xss-protection' => '1; mode=block',
        'referrer-policy' => 'strict-origin-when-cross-origin',
        'permissions-policy' => 'geolocation=(), microphone=(), camera=()',
    ],

    /*
     * Audit logging
     */
    'audit' => [
        'enabled' => true,
        'log_channel' => 'audit',
        'log_successful_logins' => true,
        'log_failed_logins' => true,
        'log_password_changes' => true,
        'log_permission_changes' => true,
        'retention_days' => 365,
    ],

    /*
     * Encryption configuration
     */
    'encryption' => [
        'sensitive_fields' => [
            'id_number',
            'bank_account',
            'tax_number',
            'phone',
        ],
        'algorithm' => 'AES-256-CBC',
        'key_rotation_days' => 90,
    ],

    /*
     * File upload security
     */
    'file_upload' => [
        'max_file_size' => 10485760, // 10MB
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'scan_for_viruses' => false,
        'quarantine_suspicious_files' => true,
    ],

    /*
     * API security
     */
    'api' => [
        'require_https' => env('APP_ENV') === 'production',
        'api_key_required' => false,
        'jwt_secret_rotation_days' => 30,
        'sanctum_expiration' => 525600, // 1 year in minutes
    ],
];
