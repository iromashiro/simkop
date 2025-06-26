<?php
// routes/auth.php - Laravel 11+ Style

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;

// SECURITY: Rate limited authentication routes
Route::middleware(['throttle.login'])->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
});

// Logout route (authenticated users only)
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
