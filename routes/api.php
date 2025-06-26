<?php
// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api;

/*
 * Public API routes
 */

Route::prefix('v1')->group(function () {
    // Health check
    Route::get('/health', [Api\SystemController::class, 'health']);
    Route::get('/ping', [Api\SystemController::class, 'ping']);
});

/*
 * Authenticated API routes
 */
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    /*
     * Tenant-aware routes
     */
    Route::middleware(['cooperative.access', 'audit.log'])->group(function () {

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [Api\DashboardController::class, 'index']);
            Route::get('/widgets', [Api\DashboardController::class, 'widgets']);
            Route::get('/statistics', [Api\DashboardController::class, 'statistics']);
            Route::get('/kpi-trends', [Api\DashboardController::class, 'kpiTrends']);
        });

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [Api\NotificationController::class, 'index']);
            Route::get('/unread-count', [Api\NotificationController::class, 'unreadCount']);
            Route::post('/', [Api\NotificationController::class, 'store']);
            Route::patch('/{notification}/read', [Api\NotificationController::class, 'markAsRead']);
            Route::patch('/mark-all-read', [Api\NotificationController::class, 'markAllAsRead']);
            Route::delete('/{notification}', [Api\NotificationController::class, 'destroy']);

            // Bulk operations
            Route::post('/bulk-send', [Api\NotificationController::class, 'bulkSend'])
                ->middleware('permission:manage_notifications');
            Route::patch('/bulk-read', [Api\NotificationController::class, 'bulkMarkAsRead']);
            Route::delete('/bulk-delete', [Api\NotificationController::class, 'bulkDelete']);

            // Templates
            Route::prefix('templates')->middleware('permission:manage_notifications')->group(function () {
                Route::get('/', [Api\NotificationController::class, 'templates']);
                Route::post('/', [Api\NotificationController::class, 'storeTemplate']);
                Route::put('/{template}', [Api\NotificationController::class, 'updateTemplate']);
                Route::delete('/{template}', [Api\NotificationController::class, 'destroyTemplate']);
            });
        });

        // User Management
        Route::prefix('users')->middleware('permission:view_users')->group(function () {
            Route::get('/', [Api\UserController::class, 'index']);
            Route::post('/', [Api\UserController::class, 'store'])->middleware('permission:create_users');
            Route::get('/{user}', [Api\UserController::class, 'show']);
            Route::put('/{user}', [Api\UserController::class, 'update'])->middleware('permission:edit_users');
            Route::delete('/{user}', [Api\UserController::class, 'destroy'])->middleware('permission:delete_users');
            Route::patch('/{user}/toggle-status', [Api\UserController::class, 'toggleStatus'])->middleware('permission:edit_users');
            Route::post('/{user}/reset-password', [Api\UserController::class, 'resetPassword'])->middleware('permission:edit_users');
        });

        // Role Management
        Route::prefix('roles')->middleware('permission:manage_user_roles')->group(function () {
            Route::get('/', [Api\RoleController::class, 'index']);
            Route::post('/', [Api\RoleController::class, 'store']);
            Route::get('/{role}', [Api\RoleController::class, 'show']);
            Route::put('/{role}', [Api\RoleController::class, 'update']);
            Route::delete('/{role}', [Api\RoleController::class, 'destroy']);
            Route::post('/{role}/permissions', [Api\RoleController::class, 'syncPermissions']);
        });

        // Permission Management
        Route::prefix('permissions')->middleware('permission:manage_user_roles')->group(function () {
            Route::get('/', [Api\PermissionController::class, 'index']);
            Route::get('/grouped', [Api\PermissionController::class, 'grouped']);
            Route::post('/check-batch', [Api\PermissionController::class, 'checkBatch']);
        });

        // Member Management
        Route::prefix('members')->middleware('permission:view_members')->group(function () {
            Route::get('/', [Api\MemberController::class, 'index']);
            Route::post('/', [Api\MemberController::class, 'store'])->middleware('permission:create_members');
            Route::get('/statistics', [Api\MemberController::class, 'statistics']);
            Route::get('/export', [Api\MemberController::class, 'export'])->middleware('permission:export_members');
            Route::get('/{member}', [Api\MemberController::class, 'show']);
            Route::put('/{member}', [Api\MemberController::class, 'update'])->middleware('permission:edit_members');
            Route::delete('/{member}', [Api\MemberController::class, 'destroy'])->middleware('permission:delete_members');
        });

        // Account Management
        Route::prefix('accounts')->middleware('permission:view_accounts')->group(function () {
            Route::get('/', [Api\AccountController::class, 'index']);
            Route::post('/', [Api\AccountController::class, 'store'])->middleware('permission:create_accounts');
            Route::get('/chart', [Api\AccountController::class, 'chartOfAccounts']);
            Route::get('/trial-balance', [Api\AccountController::class, 'trialBalance']);
            Route::get('/{account}', [Api\AccountController::class, 'show']);
            Route::put('/{account}', [Api\AccountController::class, 'update'])->middleware('permission:edit_accounts');
            Route::delete('/{account}', [Api\AccountController::class, 'destroy'])->middleware('permission:delete_accounts');
        });

        // Journal Entry Management
        Route::prefix('journal-entries')->middleware('permission:view_journal_entries')->group(function () {
            Route::get('/', [Api\JournalEntryController::class, 'index']);
            Route::post('/', [Api\JournalEntryController::class, 'store'])->middleware('permission:create_journal_entries');
            Route::get('/general-ledger', [Api\JournalEntryController::class, 'generalLedger']);
            Route::get('/{journalEntry}', [Api\JournalEntryController::class, 'show']);
            Route::put('/{journalEntry}', [Api\JournalEntryController::class, 'update'])->middleware('permission:create_journal_entries');
            Route::delete('/{journalEntry}', [Api\JournalEntryController::class, 'destroy'])->middleware('permission:create_journal_entries');
            Route::post('/{journalEntry}/approve', [Api\JournalEntryController::class, 'approve'])->middleware('permission:approve_journal_entries');
            Route::post('/{journalEntry}/reverse', [Api\JournalEntryController::class, 'reverse'])->middleware('permission:reverse_journal_entries');
        });

        // Fiscal Period Management
        Route::prefix('fiscal-periods')->middleware('permission:manage_fiscal_periods')->group(function () {
            Route::get('/', [Api\FiscalPeriodController::class, 'index']);
            Route::post('/', [Api\FiscalPeriodController::class, 'store']);
            Route::get('/current', [Api\FiscalPeriodController::class, 'current']);
            Route::get('/{fiscalPeriod}', [Api\FiscalPeriodController::class, 'show']);
            Route::put('/{fiscalPeriod}', [Api\FiscalPeriodController::class, 'update']);
            Route::post('/{fiscalPeriod}/close', [Api\FiscalPeriodController::class, 'close'])->middleware('permission:close_fiscal_periods');
            Route::post('/{fiscalPeriod}/reopen', [Api\FiscalPeriodController::class, 'reopen'])->middleware('permission:close_fiscal_periods');
            Route::post('/{fiscalPeriod}/year-end-closing', [Api\FiscalPeriodController::class, 'yearEndClosing'])->middleware('permission:close_fiscal_periods');
        });

        // Savings Management
        Route::prefix('savings')->middleware('permission:view_savings')->group(function () {
            Route::get('/', [Api\SavingsController::class, 'index']);
            Route::post('/', [Api\SavingsController::class, 'store'])->middleware('permission:create_savings_accounts');
            Route::get('/statistics', [Api\SavingsController::class, 'statistics']);
            Route::get('/{savingsAccount}', [Api\SavingsController::class, 'show']);
            Route::put('/{savingsAccount}', [Api\SavingsController::class, 'update'])->middleware('permission:create_savings_accounts');
            Route::post('/{savingsAccount}/deposit', [Api\SavingsController::class, 'deposit'])->middleware('permission:process_deposits');
            Route::post('/{savingsAccount}/withdrawal', [Api\SavingsController::class, 'withdrawal'])->middleware('permission:process_withdrawals');
            Route::get('/{savingsAccount}/transactions', [Api\SavingsController::class, 'transactions']);
        });

        // Loan Management
        Route::prefix('loans')->middleware('permission:view_loans')->group(function () {
            Route::get('/', [Api\LoanController::class, 'index']);
            Route::post('/', [Api\LoanController::class, 'store'])->middleware('permission:create_loan_accounts');
            Route::get('/statistics', [Api\LoanController::class, 'statistics']);
            Route::get('/{loanAccount}', [Api\LoanController::class, 'show']);
            Route::put('/{loanAccount}', [Api\LoanController::class, 'update'])->middleware('permission:create_loan_accounts');
            Route::post('/{loanAccount}/approve', [Api\LoanController::class, 'approve'])->middleware('permission:approve_loans');
            Route::post('/{loanAccount}/disburse', [Api\LoanController::class, 'disburse'])->middleware('permission:disburse_loans');
            Route::post('/{loanAccount}/payment', [Api\LoanController::class, 'payment'])->middleware('permission:process_payments');
            Route::get('/{loanAccount}/schedule', [Api\LoanController::class, 'paymentSchedule']);
        });

        // Reporting
        Route::prefix('reports')->middleware('permission:view_reports')->group(function () {
            Route::get('/balance-sheet', [Api\ReportController::class, 'balanceSheet']);
            Route::get('/income-statement', [Api\ReportController::class, 'incomeStatement']);
            Route::get('/cash-flow', [Api\ReportController::class, 'cashFlow']);
            Route::get('/trial-balance', [Api\ReportController::class, 'trialBalance']);
            Route::get('/general-ledger', [Api\ReportController::class, 'generalLedger']);
            Route::get('/member-report', [Api\ReportController::class, 'memberReport']);
            Route::get('/savings-report', [Api\ReportController::class, 'savingsReport']);
            Route::get('/loan-report', [Api\ReportController::class, 'loanReport']);
            Route::post('/export', [Api\ReportController::class, 'export'])->middleware('permission:export_reports');
        });

        // Workflow Management
        Route::prefix('workflows')->middleware('permission:manage_workflows')->group(function () {
            Route::get('/', [Api\WorkflowController::class, 'index']);
            Route::post('/', [Api\WorkflowController::class, 'store']);
            Route::get('/{workflow}', [Api\WorkflowController::class, 'show']);
            Route::put('/{workflow}', [Api\WorkflowController::class, 'update']);
            Route::delete('/{workflow}', [Api\WorkflowController::class, 'destroy']);
            Route::post('/{workflow}/start', [Api\WorkflowController::class, 'start']);

            // Workflow instances
            Route::get('/instances/my-tasks', [Api\WorkflowController::class, 'myTasks']);
            Route::get('/instances/{instance}', [Api\WorkflowController::class, 'showInstance']);
            Route::post('/instances/{instance}/complete-task', [Api\WorkflowController::class, 'completeTask']);
        });

        // Document Management
        Route::prefix('documents')->group(function () {
            Route::get('/', [Api\DocumentController::class, 'index']);
            Route::post('/', [Api\DocumentController::class, 'store']);
            Route::get('/{document}', [Api\DocumentController::class, 'show']);
            Route::get('/{document}/download', [Api\DocumentController::class, 'download']);
            Route::delete('/{document}', [Api\DocumentController::class, 'destroy']);
            Route::post('/{document}/share', [Api\DocumentController::class, 'share']);
        });

        // System Management
        Route::prefix('system')->middleware('permission:manage_settings')->group(function () {
            Route::get('/health', [Api\SystemController::class, 'healthCheck']);
            Route::get('/cache-stats', [Api\SystemController::class, 'cacheStats']);
            Route::post('/clear-cache', [Api\SystemController::class, 'clearCache']);
            Route::get('/performance-metrics', [Api\SystemController::class, 'performanceMetrics']);
            Route::get('/audit-logs', [Api\SystemController::class, 'auditLogs'])->middleware('permission:view_audit_logs');
        });
    });
});
