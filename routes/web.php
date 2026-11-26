<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ExtensionReportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/reports', [ExtensionReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/create', [ExtensionReportController::class, 'create'])->name('reports.create');
    Route::post('/reports', [ExtensionReportController::class, 'store'])->name('reports.store');
    Route::get('/reports/{report}', [ExtensionReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{report}/edit', [ExtensionReportController::class, 'edit'])->name('reports.edit');
    Route::put('/reports/{report}', [ExtensionReportController::class, 'update'])->name('reports.update');
    Route::delete('/reports/{report}', [ExtensionReportController::class, 'destroy'])->name('reports.destroy');
    Route::post('/reports/{report}/submit', [ExtensionReportController::class, 'submit'])->name('reports.submit');
    Route::post('/reports/{report}/verify', [ExtensionReportController::class, 'verify'])->name('reports.verify');
    Route::post('/reports/{report}/approve', [ExtensionReportController::class, 'approve'])->name('reports.approve');
    Route::post('/reports/{report}/return', [ExtensionReportController::class, 'return'])->name('reports.return');

    Route::get('/exports/reports', [ExportController::class, 'reports'])->name('exports.reports');
    Route::get('/exports/performance', [ExportController::class, 'performance'])->name('exports.performance');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

    Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
    Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
});
