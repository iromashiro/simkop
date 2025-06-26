<?php
// config/notification.php
return [
    /*
     * Default notification settings
     */
    'defaults' => [
        'channels' => ['database'],
        'queue' => 'notifications',
        'retry_attempts' => 3,
        'retry_delay' => 300, // 5 minutes
    ],

    /*
     * Available notification channels
     */
    'channels' => [
        'database' => [
            'enabled' => true,
            'driver' => App\Infrastructure\Notification\Channels\DatabaseChannel::class,
            'table' => 'notifications',
        ],

        'email' => [
            'enabled' => env('MAIL_MAILER') !== null,
            'driver' => App\Infrastructure\Notification\Channels\EmailChannel::class,
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'noreply@hermes.local'),
                'name' => env('MAIL_FROM_NAME', 'HERMES System'),
            ],
            'queue' => true,
        ],

        'sms' => [
            'enabled' => false,
            'driver' => App\Infrastructure\Notification\Channels\SMSChannel::class,
            'provider' => 'twilio', // twilio, nexmo, etc.
        ],

        'push' => [
            'enabled' => false,
            'driver' => App\Infrastructure\Notification\Channels\PushChannel::class,
            'provider' => 'fcm', // fcm, apns, etc.
        ],

        'slack' => [
            'enabled' => false,
            'driver' => App\Infrastructure\Notification\Channels\SlackChannel::class,
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
        ],
    ],

    /*
     * Notification templates
     */
    'templates' => [
        'member_welcome' => [
            'name' => 'Member Welcome',
            'channels' => ['database', 'email'],
            'subject' => 'Welcome to {{cooperative_name}}',
            'template' => 'notifications.member-welcome',
            'variables' => ['member_name', 'cooperative_name', 'member_number'],
        ],

        'loan_approved' => [
            'name' => 'Loan Approved',
            'channels' => ['database', 'email'],
            'subject' => 'Your loan has been approved',
            'template' => 'notifications.loan-approved',
            'variables' => ['member_name', 'loan_amount', 'account_number'],
        ],

        'payment_due' => [
            'name' => 'Payment Due Reminder',
            'channels' => ['database', 'email', 'sms'],
            'subject' => 'Payment Due Reminder',
            'template' => 'notifications.payment-due',
            'variables' => ['member_name', 'amount_due', 'due_date'],
        ],

        'deposit_received' => [
            'name' => 'Deposit Received',
            'channels' => ['database'],
            'subject' => 'Deposit Received',
            'template' => 'notifications.deposit-received',
            'variables' => ['member_name', 'amount', 'balance'],
        ],

        'system_maintenance' => [
            'name' => 'System Maintenance',
            'channels' => ['database', 'email'],
            'subject' => 'Scheduled System Maintenance',
            'template' => 'notifications.system-maintenance',
            'variables' => ['start_time', 'end_time', 'description'],
        ],

        'password_changed' => [
            'name' => 'Password Changed',
            'channels' => ['database', 'email'],
            'subject' => 'Password Changed Successfully',
            'template' => 'notifications.password-changed',
            'variables' => ['user_name', 'change_time', 'ip_address'],
        ],

        'suspicious_activity' => [
            'name' => 'Suspicious Activity Detected',
            'channels' => ['database', 'email', 'slack'],
            'subject' => 'Security Alert: Suspicious Activity',
            'template' => 'notifications.suspicious-activity',
            'variables' => ['user_name', 'activity', 'ip_address', 'time'],
            'priority' => 'high',
        ],
    ],

    /*
     * Notification preferences
     */
    'preferences' => [
        'allow_user_preferences' => true,
        'default_preferences' => [
            'email_notifications' => true,
            'sms_notifications' => false,
            'push_notifications' => true,
            'marketing_emails' => false,
        ],
        'required_notifications' => [
            'security_alerts',
            'account_changes',
            'system_maintenance',
        ],
    ],

    /*
     * Queue configuration
     */
    'queue' => [
        'default_queue' => 'notifications',
        'high_priority_queue' => 'high-priority',
        'low_priority_queue' => 'low-priority',
        'batch_size' => 100,
        'retry_after' => 300,
    ],

    /*
     * Rate limiting
     */
    'rate_limiting' => [
        'enabled' => true,
        'per_user_per_hour' => 50,
        'per_cooperative_per_hour' => 1000,
        'burst_limit' => 10,
    ],

    /*
     * Storage and cleanup
     */
    'storage' => [
        'retention_days' => 90,
        'cleanup_enabled' => true,
        'cleanup_schedule' => '0 2 * * *', // Daily at 2 AM
        'archive_old_notifications' => true,
    ],

    /*
     * Analytics and tracking
     */
    'analytics' => [
        'track_opens' => true,
        'track_clicks' => true,
        'track_delivery' => true,
        'generate_reports' => true,
    ],
];
