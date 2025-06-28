<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;

// Admin Controllers
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CooperativeController as AdminCooperativeController;
use App\Http\Controllers\Admin\UserManagementController as AdminUserController;
use App\Http\Controllers\Admin\ReportApprovalController as AdminReportController;

// Financial Controllers
use App\Http\Controllers\Financial\BalanceSheetController;
use App\Http\Controllers\Financial\IncomeStatementController;
use App\Http\Controllers\Financial\EquityChangesController;
use App\Http\Controllers\Financial\CashFlowController;
use App\Http\Controllers\Financial\MemberSavingsController;
use App\Http\Controllers\Financial\MemberReceivablesController;
use App\Http\Controllers\Financial\NPLReceivablesController;
use App\Http\Controllers\Financial\SHUDistributionController;
use App\Http\Controllers\Financial\BudgetPlanController;
use App\Http\Controllers\Financial\NotesToFinancialController;

// Report Controllers
use App\Http\Controllers\Reports\PDFExportController;
use App\Http\Controllers\Reports\ExcelExportController;
use App\Http\Controllers\Reports\BatchExportController;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Public Routes
Route::get('/', function () {
    return view('guest.dashboard');
})->name('guest.dashboard');

Route::get('/login', function () {
    return redirect()->route('login');
});

// Dashboard Routes (Role-based)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Profile Routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Notification Routes
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES - ADMIN DINAS ONLY
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:admin_dinas'])->prefix('admin')->name('admin.')->group(function () {

    // Admin Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Cooperative Management
    Route::resource('cooperatives', AdminCooperativeController::class);
    Route::post('cooperatives/{cooperative}/toggle-status', [AdminCooperativeController::class, 'toggleStatus'])->name('cooperatives.toggle-status');

    // User Management
    Route::resource('users', AdminUserController::class)->except(['show']);
    Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('users/{user}/send-verification', [AdminUserController::class, 'sendVerificationEmail'])->name('users.send-verification');
    Route::post('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');

    // Report Approval
    Route::get('reports/approval', [AdminReportController::class, 'index'])->name('reports.approval');
    Route::get('reports/{report}', [AdminReportController::class, 'show'])->name('reports.show');
    Route::post('reports/{report}/approve', [AdminReportController::class, 'approve'])->name('reports.approve');
    Route::post('reports/{report}/reject', [AdminReportController::class, 'reject'])->name('reports.reject');
    Route::post('reports/bulk-approve', [AdminReportController::class, 'bulkApprove'])->name('reports.bulk-approve');
    Route::post('reports/bulk-reject', [AdminReportController::class, 'bulkReject'])->name('reports.bulk-reject');
});

/*
|--------------------------------------------------------------------------
| FINANCIAL REPORTING ROUTES - COOPERATIVE USERS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:admin_koperasi|bendahara|ketua'])->prefix('financial')->name('financial.')->group(function () {

    // Balance Sheet Routes
    Route::prefix('balance-sheet')->name('balance-sheet.')->group(function () {
        Route::get('/', [BalanceSheetController::class, 'index'])->name('index');
        Route::get('/create', [BalanceSheetController::class, 'create'])->name('create');
        Route::post('/', [BalanceSheetController::class, 'store'])->name('store');
        Route::get('/{year}', [BalanceSheetController::class, 'show'])->name('show');
        Route::get('/{year}/edit', [BalanceSheetController::class, 'edit'])->name('edit');
        Route::put('/{year}', [BalanceSheetController::class, 'update'])->name('update');
        Route::delete('/{year}', [BalanceSheetController::class, 'destroy'])->name('destroy');
        Route::post('/{year}/submit', [BalanceSheetController::class, 'submit'])->name('submit');
        Route::get('/{year}/preview', [BalanceSheetController::class, 'preview'])->name('preview');
        Route::post('/validate', [BalanceSheetController::class, 'validate'])->name('validate');
    });

    // Income Statement Routes
    Route::prefix('income-statement')->name('income-statement.')->group(function () {
        Route::get('/', [IncomeStatementController::class, 'index'])->name('index');
        Route::get('/create', [IncomeStatementController::class, 'create'])->name('create');
        Route::post('/', [IncomeStatementController::class, 'store'])->name('store');
        Route::get('/{year}', [IncomeStatementController::class, 'show'])->name('show');
        Route::get('/{year}/edit', [IncomeStatementController::class, 'edit'])->name('edit');
        Route::put('/{year}', [IncomeStatementController::class, 'update'])->name('update');
        Route::delete('/{year}', [IncomeStatementController::class, 'destroy'])->name('destroy');
        Route::post('/{year}/submit', [IncomeStatementController::class, 'submit'])->name('submit');
        Route::get('/{year}/preview', [IncomeStatementController::class, 'preview'])->name('preview');
        Route::post('/validate', [IncomeStatementController::class, 'validate'])->name('validate');
    });

    // Equity Changes Routes
    Route::prefix('equity-changes')->name('equity-changes.')->group(function () {
        Route::get('/', [EquityChangesController::class, 'index'])->name('index');
        Route::get('/create', [EquityChangesController::class, 'create'])->name('create');
        Route::post('/', [EquityChangesController::class, 'store'])->name('store');
        Route::get('/{year}', [EquityChangesController::class, 'show'])->name('show');
        Route::get('/{year}/edit', [EquityChangesController::class, 'edit'])->name('edit');
        Route::put('/{year}', [EquityChangesController::class, 'update'])->name('update');
        Route::delete('/{year}', [EquityChangesController::class, 'destroy'])->name('destroy');
        Route::post('/{year}/submit', [EquityChangesController::class, 'submit'])->name('submit');
        Route::get('/{year}/preview', [EquityChangesController::class, 'preview'])->name('preview');
        Route::post('/validate', [EquityChangesController::class, 'validate'])->name('validate');
    });

    // Cash Flow Routes
    Route::prefix('cash-flow')->name('cash-flow.')->group(function () {
        Route::get('/', [CashFlowController::class, 'index'])->name('index');
        Route::get('/create', [CashFlowController::class, 'create'])->name('create');
        Route::post('/', [CashFlowController::class, 'store'])->name('store');
        Route::get('/{year}', [CashFlowController::class, 'show'])->name('show');
        Route::get('/{year}/edit', [CashFlowController::class, 'edit'])->name('edit');
        Route::put('/{year}', [CashFlowController::class, 'update'])->name('update');
        Route::delete('/{year}', [CashFlowController::class, 'destroy'])->name('destroy');
        Route::post('/{year}/submit', [CashFlowController::class, 'submit'])->name('submit');
        Route::get('/{year}/preview', [CashFlowController::class, 'preview'])->name('preview');
        Route::post('/validate', [CashFlowController::class, 'validate'])->name('validate');
    });

    // Member Savings Routes
    Route::prefix('member-savings')->name('member-savings.')->group(function () {
        Route::get('/', [MemberSavingsController::class, 'index'])->name('index');
        Route::get('/create', [MemberSavingsController::class, 'create'])->name('create');
        Route::post('/', [MemberSavingsController::class, 'store'])->name('store');
        Route::get('/{year}', [MemberSavingsController::class, 'show'])->name('show');
        Route::get('/{year}/edit', [MemberSavingsController::class, 'edit'])->name('edit');
        Route::put('/{year}', [MemberSavingsController::class, 'update'])->name('update');
        Route::delete('/{year}', [MemberSavingsController::class, 'destroy'])->name('destroy');
        Route::post('/{year}/submit', [MemberSavingsController::class, 'submit'])->name('submit');
        Route::get('/{year}/preview', [MemberSavingsController::class, 'preview'])->name('preview');
        Route::post('/validate', [MemberSavingsController::class, 'validate'])->name('validate');
    });

    // Member Receivables Routes
    Route::prefix('member-receivables')->name('member-receivables.')->group(function () {
        Route::get('/', [MemberReceivablesController::class, 'index'])->name('index');
        Route::get('/create', [MemberReceivablesController::class, 'create'])->name('create');
        Route::post('/', [MemberReceivablesController::class, 'store'])->name('store');
        Route::get('/{year}', [MemberReceivablesController::class, 'show'])->name('show');
        Route::get('/{year}/edit', [MemberReceivablesController::class, 'edit'])->name('edit');
        Route::put('/{year}', [MemberReceivablesController::class, 'update'])->name('update');
        Route::delete('/{year}', [MemberReceivablesController::class, 'destroy'])->name('destroy');
        Route::post('/{year}/submit', [MemberReceivablesController::class, 'submit'])->name('submit');
        Route::get('/{year}/preview', [MemberReceivablesController::class, 'preview'])->name('preview');
        Route::post('/validate', [MemberReceivablesController::class, 'validate'])->name('validate');
    });

    // NPL Receivables Routes
    Route::prefix('npl-receivables')->name('npl-receivables.')->group(function () {
        Route::get('/', [NPLReceivablesController::class, 'index'])->name('index');
        Route::get('/create', [NPLReceivablesController::class, 'create'])->name('create');
        Route::post('/', [NPLReceivablesController::class, 'store'])->name('store');
        Route::get('/{year}', [NPLReceivablesController::class, 'show'])->name('show');
        Route::get('/{year}/edit', [NPLReceivablesController::class, 'edit'])->name('edit');
        Route::put('/{year}', [NPLReceivablesController::class, 'update'])->name('update');
        Route::delete('/{year}', [NPLReceivablesController::class, 'destroy'])->name('destroy');
        Route::post('/{year}/submit', [NPLReceivablesController::class, 'submit'])->name('submit');
        Route::get('/{year}/preview', [NPLReceivablesController::class, 'preview'])->name('preview');
        Route::post('/validate', [NPLReceivablesController::class, 'validate'])->name('validate');
    });

    // SHU Distribution Routes
    Route::prefix('shu-distribution')->name('shu-distribution.')->group(function () {
        Route::get('/', [SHUDistributionController::class, 'index'])->name('index');
        Route::get('/create', [SHUDistributionController::class, 'create'])->name('create');
        Route::post('/', [SHUDistributionController::class, 'store'])->name('store');
        Route::get('/{year}', [SHUDistributionController::class, 'show'])->name('show');
        Route::get('/{year}/edit', [SHUDistributionController::class, 'edit'])->name('edit');
        Route::put('/{year}', [SHUDistributionController::class, 'update'])->name('update');
        Route::delete('/{year}', [SHUDistributionController::class, 'destroy'])->name('destroy');
        Route::post('/{year}/submit', [SHUDistributionController::class, 'submit'])->name('submit');
        Route::get('/{year}/preview', [SHUDistributionController::class, 'preview'])->name('preview');
        Route::post('/validate', [SHUDistributionController::class, 'validate'])->name('validate');
    });

    // Budget Plan Routes
    Route::prefix('budget-plan')->name('budget-plan.')->group(function () {
        Route::get('/', [BudgetPlanController::class, 'index'])->name('index');
        Route::get('/create', [BudgetPlanController::class, 'create'])->name('create');
        Route::post('/', [BudgetPlanController::class, 'store'])->name('store');
        Route::get('/{year}', [BudgetPlanController::class, 'show'])->name('show');
        Route::get('/{year}/edit', [BudgetPlanController::class, 'edit'])->name('edit');
        Route::put('/{year}', [BudgetPlanController::class, 'update'])->name('update');
        Route::delete('/{year}', [BudgetPlanController::class, 'destroy'])->name('destroy');
        Route::post('/{year}/submit', [BudgetPlanController::class, 'submit'])->name('submit');
        Route::get('/{year}/preview', [BudgetPlanController::class, 'preview'])->name('preview');
        Route::post('/validate', [BudgetPlanController::class, 'validate'])->name('validate');
    });

    // Notes to Financial Statements Routes
    Route::prefix('notes')->name('notes.')->group(function () {
        Route::get('/', [NotesToFinancialController::class, 'index'])->name('index');
        Route::get('/create', [NotesToFinancialController::class, 'create'])->name('create');
        Route::post('/', [NotesToFinancialController::class, 'store'])->name('store');
        Route::get('/{year}', [NotesToFinancialController::class, 'show'])->name('show');
        Route::get('/{year}/edit', [NotesToFinancialController::class, 'edit'])->name('edit');
        Route::put('/{year}', [NotesToFinancialController::class, 'update'])->name('update');
        Route::delete('/{year}', [NotesToFinancialController::class, 'destroy'])->name('destroy');
        Route::post('/{year}/submit', [NotesToFinancialController::class, 'submit'])->name('submit');
        Route::get('/{year}/preview', [NotesToFinancialController::class, 'preview'])->name('preview');
        Route::post('/validate', [NotesToFinancialController::class, 'validate'])->name('validate');
    });
});

/*
|--------------------------------------------------------------------------
| REPORT EXPORT ROUTES - ALL AUTHENTICATED USERS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->prefix('reports')->name('reports.')->group(function () {

    // PDF Export Routes
    Route::get('/pdf/{report}', [PDFExportController::class, 'exportSingle'])->name('export.pdf');
    Route::get('/pdf/balance-sheet/{year}', [PDFExportController::class, 'exportBalanceSheet'])->name('export.balance-sheet.pdf');
    Route::get('/pdf/income-statement/{year}', [PDFExportController::class, 'exportIncomeStatement'])->name('export.income-statement.pdf');
    Route::get('/pdf/equity-changes/{year}', [PDFExportController::class, 'exportEquityChanges'])->name('export.equity-changes.pdf');
    Route::get('/pdf/cash-flow/{year}', [PDFExportController::class, 'exportCashFlow'])->name('export.cash-flow.pdf');
    Route::get('/pdf/member-savings/{year}', [PDFExportController::class, 'exportMemberSavings'])->name('export.member-savings.pdf');
    Route::get('/pdf/member-receivables/{year}', [PDFExportController::class, 'exportMemberReceivables'])->name('export.member-receivables.pdf');
    Route::get('/pdf/npl-receivables/{year}', [PDFExportController::class, 'exportNPLReceivables'])->name('export.npl-receivables.pdf');
    Route::get('/pdf/shu-distribution/{year}', [PDFExportController::class, 'exportSHUDistribution'])->name('export.shu-distribution.pdf');
    Route::get('/pdf/budget-plan/{year}', [PDFExportController::class, 'exportBudgetPlan'])->name('export.budget-plan.pdf');
    Route::get('/pdf/notes/{year}', [PDFExportController::class, 'exportNotes'])->name('export.notes.pdf');
    Route::get('/pdf/complete/{year}', [PDFExportController::class, 'exportComplete'])->name('export.complete.pdf');

    // Excel Export Routes
    Route::get('/excel/{report}', [ExcelExportController::class, 'exportSingle'])->name('export.excel');
    Route::get('/excel/balance-sheet/{year}', [ExcelExportController::class, 'exportBalanceSheet'])->name('export.balance-sheet.excel');
    Route::get('/excel/income-statement/{year}', [ExcelExportController::class, 'exportIncomeStatement'])->name('export.income-statement.excel');
    Route::get('/excel/equity-changes/{year}', [ExcelExportController::class, 'exportEquityChanges'])->name('export.equity-changes.excel');
    Route::get('/excel/cash-flow/{year}', [ExcelExportController::class, 'exportCashFlow'])->name('export.cash-flow.excel');
    Route::get('/excel/member-savings/{year}', [ExcelExportController::class, 'exportMemberSavings'])->name('export.member-savings.excel');
    Route::get('/excel/member-receivables/{year}', [ExcelExportController::class, 'exportMemberReceivables'])->name('export.member-receivables.excel');
    Route::get('/excel/npl-receivables/{year}', [ExcelExportController::class, 'exportNPLReceivables'])->name('export.npl-receivables.excel');
    Route::get('/excel/shu-distribution/{year}', [ExcelExportController::class, 'exportSHUDistribution'])->name('export.shu-distribution.excel');
    Route::get('/excel/budget-plan/{year}', [ExcelExportController::class, 'exportBudgetPlan'])->name('export.budget-plan.excel');
    Route::get('/excel/notes/{year}', [ExcelExportController::class, 'exportNotes'])->name('export.notes.excel');
    Route::get('/excel/complete/{year}', [ExcelExportController::class, 'exportComplete'])->name('export.complete.excel');

    // Batch Export Routes
    Route::post('/batch/pdf', [BatchExportController::class, 'exportPDFBatch'])->name('export.batch.pdf');
    Route::post('/batch/excel', [BatchExportController::class, 'exportExcelBatch'])->name('export.batch.excel');
    Route::get('/batch/download/{filename}', [BatchExportController::class, 'downloadBatch'])->name('export.batch.download');
    Route::get('/batch/status/{jobId}', [BatchExportController::class, 'getBatchStatus'])->name('export.batch.status');
});

/*
|--------------------------------------------------------------------------
| COMPARISON & ANALYTICS ROUTES - ALL AUTHENTICATED USERS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/year-over-year/{startYear}/{endYear}', [FinancialAnalyticsController::class, 'yearOverYear'])->name('year-over-year');
    Route::get('/trends/{reportType}', [FinancialAnalyticsController::class, 'trends'])->name('trends');
    Route::get('/ratios/{year}', [FinancialAnalyticsController::class, 'ratios'])->name('ratios');
    Route::get('/benchmarks', [FinancialAnalyticsController::class, 'benchmarks'])->name('benchmarks');
});

/*
|--------------------------------------------------------------------------
| API ROUTES FOR AJAX CALLS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->prefix('api')->name('api.')->group(function () {

    // Notification API
    Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Dashboard Data API
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats'])->name('dashboard.stats');
    Route::get('/dashboard/charts/{type}', [DashboardController::class, 'getChartData'])->name('dashboard.charts');

    // Financial Validation API
    Route::post('/financial/validate-balance-sheet', [BalanceSheetController::class, 'validateData'])->name('financial.validate.balance-sheet');
    Route::post('/financial/validate-income-statement', [IncomeStatementController::class, 'validateData'])->name('financial.validate.income-statement');
    Route::post('/financial/validate-cash-flow', [CashFlowController::class, 'validateData'])->name('financial.validate.cash-flow');

    // Auto-save API
    Route::post('/financial/auto-save/{reportType}/{year}', [FinancialController::class, 'autoSave'])->name('financial.auto-save');

    // Search API
    Route::get('/search/cooperatives', [AdminCooperativeController::class, 'search'])->name('search.cooperatives');
    Route::get('/search/users', [AdminUserController::class, 'search'])->name('search.users');
    Route::get('/search/reports', [AdminReportController::class, 'search'])->name('search.reports');
});

// Include auth routes
require __DIR__ . '/auth.php';
