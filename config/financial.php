<?php
// config/financial.php

return [
    'pagination' => [
        'default_per_page' => 15,
        'max_per_page' => 50,
    ],

    'validation' => [
        'max_amount_increase_percentage' => 1000, // 1000% increase threshold
        'balance_tolerance' => 0.01, // Balance equation tolerance
    ],

    'cache' => [
        'dashboard_ttl' => 3600, // 1 hour
        'reports_ttl' => 1800,   // 30 minutes
    ],

    'status' => [
        'DRAFT' => 'draft',
        'SUBMITTED' => 'submitted',
        'APPROVED' => 'approved',
        'REJECTED' => 'rejected',
    ],

    'report_types' => [
        'BALANCE_SHEET' => 'balance_sheet',
        'INCOME_STATEMENT' => 'income_statement',
        'EQUITY_CHANGES' => 'equity_changes',
        'CASH_FLOW' => 'cash_flow',
        'MEMBER_SAVINGS' => 'member_savings',
        'MEMBER_RECEIVABLES' => 'member_receivables',
        'NPL_RECEIVABLES' => 'npl_receivables',
        'SHU_DISTRIBUTION' => 'shu_distribution',
        'BUDGET_PLAN' => 'budget_plan',
    ],
];
