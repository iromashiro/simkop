<?php
// routes/web.php - Laravel 11+ with enhanced security

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\CooperativeController;
use App\Http\Controllers\Web\Financial\AccountController;
use App\Http\Controllers\Web\Financial\JournalEntryController;
use App\Http\Controllers\Web\Member\MemberController;
use App\Http\Controllers\Web\Member\SavingsController;

// Public routes
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication routes
require __DIR__ . '/auth.php';

// SECURITY: Authenticated and tenant-aware routes
Route::middleware(['auth', 'tenant'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Cooperative management (admin only)
    Route::middleware(['can:manage-cooperative'])->group(function () {
        Route::resource('cooperative', CooperativeController::class)->except(['index', 'create', 'store']);
    });

    // Financial routes with additional security
    Route::middleware(['financial'])->prefix('financial')->name('financial.')->group(function () {
        Route::resource('accounts', AccountController::class);
        Route::resource('journal-entries', JournalEntryController::class);

        // Special routes for financial operations
        Route::post('journal-entries/{journalEntry}/approve', [JournalEntryController::class, 'approve'])
            ->name('journal-entries.approve')
            ->middleware(['can:approve-journal-entries']);
    });

    // Member management
    Route::prefix('members')->name('members.')->group(function () {
        Route::resource('/', MemberController::class)->parameters(['' => 'member']);

        // Savings management
        Route::prefix('{member}/savings')->name('savings.')->group(function () {
            Route::get('/', [SavingsController::class, 'index'])->name('index');
            Route::post('/deposit', [SavingsController::class, 'deposit'])->name('deposit');
            Route::post('/withdraw', [SavingsController::class, 'withdraw'])->name('withdraw');
        });
    });
});

// SECURITY: Super admin routes
Route::middleware(['auth', 'role:Super Admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/cooperatives', [CooperativeController::class, 'index'])->name('cooperatives.index');
    Route::post('/cooperatives', [CooperativeController::class, 'store'])->name('cooperatives.store');
    Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditController::class, 'index'])->name('audit-logs');
    Route::get('/system-health', [\App\Http\Controllers\Admin\SystemController::class, 'health'])->name('system-health');
});
