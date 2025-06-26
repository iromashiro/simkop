<?php
// config/permission.php
return [
    /*
     * Model definitions for Laravel 11
     */
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],

    /*
     * Table names for Laravel 11
     */
    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    /*
     * Column names for Laravel 11
     */
    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],

    /*
     * Register the permission check method
     */
    'register_permission_check_method' => true,
    'register_octane_reset_listener' => false,

    /*
     * Teams feature for multi-tenancy
     */
    'teams' => false,
    'use_passport_client_credentials' => false,

    /*
     * Cache configuration for Laravel 11
     */
    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],

    /*
     * HERMES specific permissions
     */
    'hermes_permissions' => [
        // Cooperative Management
        'cooperative' => [
            'view_cooperative',
            'edit_cooperative',
            'manage_cooperative_settings',
            'view_cooperative_statistics',
        ],

        // User Management
        'users' => [
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'manage_user_roles',
            'view_user_activity',
        ],

        // Member Management
        'members' => [
            'view_members',
            'create_members',
            'edit_members',
            'delete_members',
            'view_member_statistics',
            'export_members',
        ],

        // Financial Management
        'financial' => [
            'view_accounts',
            'create_accounts',
            'edit_accounts',
            'delete_accounts',
            'view_journal_entries',
            'create_journal_entries',
            'approve_journal_entries',
            'reverse_journal_entries',
            'manage_fiscal_periods',
            'close_fiscal_periods',
        ],

        // Savings Management
        'savings' => [
            'view_savings',
            'create_savings_accounts',
            'process_deposits',
            'process_withdrawals',
            'view_savings_reports',
        ],

        // Loan Management
        'loans' => [
            'view_loans',
            'create_loan_accounts',
            'approve_loans',
            'disburse_loans',
            'process_payments',
            'view_loan_reports',
        ],

        // Reporting
        'reports' => [
            'view_reports',
            'generate_financial_reports',
            'export_reports',
            'view_analytics',
        ],

        // System Administration
        'system' => [
            'manage_settings',
            'view_audit_logs',
            'manage_notifications',
            'manage_workflows',
            'view_system_health',
        ],
    ],
];
