<?php
// config/reporting.php
return [
    /*
     * Default report settings
     */
    'defaults' => [
        'format' => 'pdf',
        'orientation' => 'portrait',
        'paper_size' => 'a4',
        'font_family' => 'Arial',
        'font_size' => 12,
    ],

    /*
     * Report types configuration
     */
    'report_types' => [
        'balance_sheet' => [
            'name' => 'Balance Sheet',
            'class' => App\Domain\Reporting\Reports\BalanceSheetReport::class,
            'template' => 'reports.balance-sheet',
            'formats' => ['pdf', 'excel', 'html'],
            'cache_ttl' => 1800, // 30 minutes
        ],

        'income_statement' => [
            'name' => 'Income Statement',
            'class' => App\Domain\Reporting\Reports\IncomeStatementReport::class,
            'template' => 'reports.income-statement',
            'formats' => ['pdf', 'excel', 'html'],
            'cache_ttl' => 1800,
        ],

        'cash_flow' => [
            'name' => 'Cash Flow Statement',
            'class' => App\Domain\Reporting\Reports\CashFlowReport::class,
            'template' => 'reports.cash-flow',
            'formats' => ['pdf', 'excel', 'html'],
            'cache_ttl' => 1800,
        ],

        'trial_balance' => [
            'name' => 'Trial Balance',
            'class' => App\Domain\Reporting\Reports\TrialBalanceReport::class,
            'template' => 'reports.trial-balance',
            'formats' => ['pdf', 'excel', 'html'],
            'cache_ttl' => 900, // 15 minutes
        ],

        'general_ledger' => [
            'name' => 'General Ledger',
            'class' => App\Domain\Reporting\Reports\GeneralLedgerReport::class,
            'template' => 'reports.general-ledger',
            'formats' => ['pdf', 'excel'],
            'cache_ttl' => 1800,
        ],

        'member_report' => [
            'name' => 'Member Report',
            'class' => App\Domain\Reporting\Reports\MemberReport::class,
            'template' => 'reports.member-report',
            'formats' => ['pdf', 'excel'],
            'cache_ttl' => 3600, // 1 hour
        ],

        'savings_report' => [
            'name' => 'Savings Report',
            'class' => App\Domain\Reporting\Reports\SavingsReport::class,
            'template' => 'reports.savings-report',
            'formats' => ['pdf', 'excel'],
            'cache_ttl' => 1800,
        ],

        'loan_report' => [
            'name' => 'Loan Report',
            'class' => App\Domain\Reporting\Reports\LoanReport::class,
            'template' => 'reports.loan-report',
            'formats' => ['pdf', 'excel'],
            'cache_ttl' => 1800,
        ],

        'shu_calculation' => [
            'name' => 'SHU Calculation Report',
            'class' => App\Domain\Reporting\Reports\SHUCalculationReport::class,
            'template' => 'reports.shu-calculation',
            'formats' => ['pdf', 'excel'],
            'cache_ttl' => 7200, // 2 hours
        ],

        'budget_report' => [
            'name' => 'Budget Report',
            'class' => App\Domain\Reporting\Reports\BudgetReport::class,
            'template' => 'reports.budget-report',
            'formats' => ['pdf', 'excel'],
            'cache_ttl' => 3600,
        ],
    ],

    /*
     * Export configuration
     */
    'export' => [
        'pdf' => [
            'engine' => 'dompdf', // dompdf, wkhtmltopdf, tcpdf
            'options' => [
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'Arial',
                'dpi' => 150,
                'defaultPaperSize' => 'a4',
                'chroot' => storage_path('app/reports'),
            ],
        ],

        'excel' => [
            'engine' => 'maatwebsite', // maatwebsite/excel
            'options' => [
                'format' => 'xlsx',
                'auto_size' => true,
                'include_charts' => true,
            ],
        ],

        'csv' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
            'encoding' => 'UTF-8',
        ],
    ],

    /*
     * Storage configuration
     */
    'storage' => [
        'disk' => 'reports',
        'path' => 'reports',
        'retention_days' => 30,
        'cleanup_enabled' => true,
    ],

    /*
     * Performance configuration
     */
    'performance' => [
        'queue_large_reports' => true,
        'large_report_threshold' => 10000, // rows
        'chunk_size' => 1000,
        'memory_limit' => '512M',
        'max_execution_time' => 300, // 5 minutes
    ],

    /*
     * Security configuration
     */
    'security' => [
        'require_authentication' => true,
        'log_report_access' => true,
        'watermark_enabled' => true,
        'watermark_text' => 'CONFIDENTIAL',
    ],
];
