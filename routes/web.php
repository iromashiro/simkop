<?php
// routes/web.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web;

/*
 * Public routes
 */

Route::get('/', function () {
    return redirect()->route('dashboard');
});

/*
 * Authentication routes (Laravel 11 Breeze/Jetstream)
 */
require __DIR__ . '/auth.php';

/*
 * Authenticated web routes
 */
Route::middleware(['auth', 'verified'])->group(function () {

    /*
     * Tenant-aware routes
     */
    Route::middleware(['cooperative.access', 'audit.log'])->group(function () {

        // Dashboard
        Route::get('/dashboard', [Web\DashboardController::class, 'index'])->name('dashboard');

        // Cooperative Management
        Route::prefix('cooperative')->name('cooperative.')->group(function () {
            Route::get('/', [Web\CooperativeController::class, 'show'])->name('show');
            Route::get('/edit', [Web\CooperativeController::class, 'edit'])->name('edit')->middleware('permission:edit_cooperative');
            Route::put('/', [Web\CooperativeController::class, 'update'])->name('update')->middleware('permission:edit_cooperative');
            Route::get('/settings', [Web\CooperativeController::class, 'settings'])->name('settings')->middleware('permission:manage_cooperative_settings');
            Route::put('/settings', [Web\CooperativeController::class, 'updateSettings'])->name('settings.update')->middleware('permission:manage_cooperative_settings');
        });

        // User Management
        Route::resource('users', Web\UserController::class)->middleware('permission:view_users');
        Route::prefix('users')->name('users.')->group(function () {
            Route::patch('{user}/toggle-status', [Web\UserController::class, 'toggleStatus'])->name('toggle-status')->middleware('permission:edit_users');
            Route::get('{user}/profile', [Web\UserController::class, 'profile'])->name('profile');
            Route::put('{user}/profile', [Web\UserController::class, 'updateProfile'])->name('profile.update');
            Route::post('{user}/reset-password', [Web\UserController::class, 'resetPassword'])->name('reset-password')->middleware('permission:edit_users');
            Route::get('export', [Web\UserController::class, 'export'])->name('export')->middleware('permission:view_users');
        });

        // Member Management
        Route::resource('members', Web\MemberController::class)->middleware('permission:view_members');
        Route::prefix('members')->name('members.')->group(function () {
            Route::get('export', [Web\MemberController::class, 'export'])->name('export')->middleware('permission:export_members');
        });

        // Account Management
        Route::resource('accounts', Web\AccountController::class)->middleware('permission:view_accounts');
        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('chart', [Web\AccountController::class, 'chartOfAccounts'])->name('chart');
            Route::get('trial-balance', [Web\AccountController::class, 'trialBalance'])->name('trial-balance');
            Route::get('import', [Web\AccountController::class, 'import'])->name('import')->middleware('permission:create_accounts');
            Route::post('import', [Web\AccountController::class, 'processImport'])->name('import.process')->middleware('permission:create_accounts');
            Route::get('export', [Web\AccountController::class, 'export'])->name('export');
        });

        // Journal Entry Management
        Route::resource('journal-entries', Web\JournalEntryController::class)->middleware('permission:view_journal_entries');
        Route::prefix('journal-entries')->name('journal-entries.')->group(function () {
            Route::get('general-ledger', [Web\JournalEntryController::class, 'generalLedger'])->name('general-ledger');
            Route::post('{journalEntry}/approve', [Web\JournalEntryController::class, 'approve'])->name('approve')->middleware('permission:approve_journal_entries');
            Route::post('{journalEntry}/reverse', [Web\JournalEntryController::class, 'reverse'])->name('reverse')->middleware('permission:reverse_journal_entries');
            Route::get('export', [Web\JournalEntryController::class, 'export'])->name('export');
        });

        // Fiscal Period Management
        Route::resource('fiscal-periods', Web\FiscalPeriodController::class)->middleware('permission:manage_fiscal_periods');
        Route::prefix('fiscal-periods')->name('fiscal-periods.')->group(function () {
            Route::post('{fiscalPeriod}/close', [Web\FiscalPeriodController::class, 'close'])->name('close')->middleware('permission:close_fiscal_periods');
            Route::post('{fiscalPeriod}/reopen', [Web\FiscalPeriodController::class, 'reopen'])->name('reopen')->middleware('permission:close_fiscal_periods');
            Route::get('{fiscalPeriod}/year-end-closing', [Web\FiscalPeriodController::class, 'yearEndClosing'])->name('year-end-closing')->middleware('permission:close_fiscal_periods');
            Route::post('{fiscalPeriod}/process-year-end-closing', [Web\FiscalPeriodController::class, 'processYearEndClosing'])->name('process-year-end-closing')->middleware('permission:close_fiscal_periods');
        });

        // Savings Management
        Route::resource('savings', Web\SavingsController::class)->middleware('permission:view_savings');
        Route::prefix('savings')->name('savings.')->group(function () {
            Route::get('{savingsAccount}/deposit', [Web\SavingsController::class, 'deposit'])->name('deposit')->middleware('permission:process_deposits');
            Route::post('{savingsAccount}/deposit', [Web\SavingsController::class, 'processDeposit'])->name('deposit.process')->middleware('permission:process_deposits');
            Route::get('{savingsAccount}/withdrawal', [Web\SavingsController::class, 'withdrawal'])->name('withdrawal')->middleware('permission:process_withdrawals');
            Route::post('{savingsAccount}/withdrawal', [Web\SavingsController::class, 'processWithdrawal'])->name('withdrawal.process')->middleware('permission:process_withdrawals');
            Route::get('export', [Web\SavingsController::class, 'export'])->name('export');
        });

        // Loan Management
        Route::resource('loans', Web\LoanController::class)->middleware('permission:view_loans');
        Route::prefix('loans')->name('loans.')->group(function () {
            Route::get('{loanAccount}/payment', [Web\LoanController::class, 'payment'])->name('payment')->middleware('permission:process_payments');
            Route::post('{loanAccount}/payment', [Web\LoanController::class, 'processPayment'])->name('payment.process')->middleware('permission:process_payments');
            Route::post('{loanAccount}/approve', [Web\LoanController::class, 'approve'])->name('approve')->middleware('permission:approve_loans');
            Route::post('{loanAccount}/disburse', [Web\LoanController::class, 'disburse'])->name('disburse')->middleware('permission:disburse_loans');
            Route::get('export', [Web\LoanController::class, 'export'])->name('export');
        });

        // Reporting
        Route::prefix('reports')->name('reports.')->middleware('permission:view_reports')->group(function () {
            Route::get('/', [Web\ReportController::class, 'index'])->name('index');
            Route::get('/balance-sheet', [Web\ReportController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('/income-statement', [Web\ReportController::class, 'incomeStatement'])->name('income-statement');
            Route::get('/cash-flow', [Web\ReportController::class, 'cashFlow'])->name('cash-flow');
            Route::get('/member-report', [Web\ReportController::class, 'memberReport'])->name('member-report');
            Route::get('/savings-report', [Web\ReportController::class, 'savingsReport'])->name('savings-report');
            Route::get('/loan-report', [Web\ReportController::class, 'loanReport'])->name('loan-report');
            Route::post('/export', [Web\ReportController::class, 'export'])->name('export')->middleware('permission:export_reports');
        });

        // Settings
        Route::prefix('settings')->name('settings.')->middleware('permission:manage_settings')->group(function () {
            Route::get('/', [Web\SettingsController::class, 'index'])->name('index');
            Route::get('/general', [Web\SettingsController::class, 'general'])->name('general');
            Route::get('/financial', [Web\SettingsController::class, 'financial'])->name('financial');
            Route::get('/notifications', [Web\SettingsController::class, 'notifications'])->name('notifications');
            Route::put('/', [Web\SettingsController::class, 'update'])->name('update');
            Route::get('/backup', [Web\SettingsController::class, 'backup'])->name('backup');
            Route::post('/backup', [Web\SettingsController::class, 'createBackup'])->name('backup.create');
        });
    });
});
