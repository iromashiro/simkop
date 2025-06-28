<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FinancialController;
use App\Http\Controllers\Api\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED API ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // Notifications API
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{notification}/mark-read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
    });

    // Dashboard API
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/stats', [DashboardController::class, 'getStats']);
        Route::get('/recent-reports', [DashboardController::class, 'getRecentReports']);
        Route::get('/pending-approvals', [DashboardController::class, 'getPendingApprovals']);
        Route::get('/chart-data/{type}', [DashboardController::class, 'getChartData']);
        Route::get('/cooperative-stats/{cooperative}', [DashboardController::class, 'getCooperativeStats']);
    });

    // Financial Data API
    Route::prefix('financial')->name('financial.')->group(function () {
        Route::post('/validate/{reportType}', [FinancialController::class, 'validateData']);
        Route::post('/auto-save/{reportType}/{year}', [FinancialController::class, 'autoSave']);
        Route::get('/summary/{year}', [FinancialController::class, 'getSummary']);
        Route::get('/comparison/{startYear}/{endYear}', [FinancialController::class, 'getComparison']);
        Route::post('/calculate-ratios', [FinancialController::class, 'calculateRatios']);
    });

    // Report Status API
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/status/{report}', [ReportController::class, 'getStatus']);
        Route::get('/export-progress/{jobId}', [ReportController::class, 'getExportProgress']);
        Route::post('/batch-status', [ReportController::class, 'getBatchStatus']);
    });

    // Search API
    Route::prefix('search')->name('search.')->group(function () {
        Route::get('/cooperatives', [SearchController::class, 'cooperatives']);
        Route::get('/users', [SearchController::class, 'users']);
        Route::get('/reports', [SearchController::class, 'reports']);
        Route::get('/financial-data', [SearchController::class, 'financialData']);
    });

    // File Upload API
    Route::prefix('upload')->name('upload.')->group(function () {
        Route::post('/financial-document', [FileUploadController::class, 'uploadFinancialDocument']);
        Route::post('/supporting-document', [FileUploadController::class, 'uploadSupportingDocument']);
        Route::delete('/document/{document}', [FileUploadController::class, 'deleteDocument']);
    });
});

/*
|--------------------------------------------------------------------------
| ADMIN API ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin_dinas'])->prefix('admin')->name('admin.')->group(function () {

    // System Statistics
    Route::get('/system-stats', [AdminApiController::class, 'getSystemStats']);
    Route::get('/cooperative-performance', [AdminApiController::class, 'getCooperativePerformance']);
    Route::get('/user-activity', [AdminApiController::class, 'getUserActivity']);
    Route::get('/report-trends', [AdminApiController::class, 'getReportTrends']);

    // Bulk Operations
    Route::post('/cooperatives/bulk-action', [AdminApiController::class, 'bulkCooperativeAction']);
    Route::post('/users/bulk-action', [AdminApiController::class, 'bulkUserAction']);
    Route::post('/reports/bulk-approval', [AdminApiController::class, 'bulkReportApproval']);

    // Export Operations
    Route::post('/export/cooperatives', [AdminApiController::class, 'exportCooperatives']);
    Route::post('/export/users', [AdminApiController::class, 'exportUsers']);
    Route::post('/export/reports', [AdminApiController::class, 'exportReports']);
});

/*
|--------------------------------------------------------------------------
| WEBHOOK ROUTES (NO AUTH REQUIRED)
|--------------------------------------------------------------------------
*/
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/export-completed', [WebhookController::class, 'exportCompleted']);
    Route::post('/notification-delivered', [WebhookController::class, 'notificationDelivered']);
    Route::post('/system-health', [WebhookController::class, 'systemHealth']);
});
