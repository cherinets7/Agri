<?php

use App\Enums\PeriodicReportType;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ExtensionReportController;
use App\Http\Controllers\InputDispatchController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PeriodicReportController;
use App\Http\Controllers\PlanMatrixController;
use App\Http\Controllers\PlanStructureController;
use App\Http\Controllers\PlanTargetController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeeklyPlanReportController;
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
    Route::get('/matrix', [PlanMatrixController::class, 'index'])->name('matrix.index');
    Route::get('/exports/matrix', [ExportController::class, 'matrix'])->name('exports.matrix');

    $periodicTypes = array_column(PeriodicReportType::cases(), 'value');
    Route::get('/periodic-reports', [PeriodicReportController::class, 'hub'])->name('periodic.hub');
    Route::get('/periodic-reports/type/{type}', [PeriodicReportController::class, 'index'])
        ->whereIn('type', $periodicTypes)->name('periodic.index');
    Route::get('/periodic-reports/type/{type}/create', [PeriodicReportController::class, 'create'])
        ->whereIn('type', $periodicTypes)->name('periodic.create');
    Route::post('/periodic-reports/type/{type}', [PeriodicReportController::class, 'store'])
        ->whereIn('type', $periodicTypes)->name('periodic.store');
    Route::get('/periodic-reports/{periodicReport}', [PeriodicReportController::class, 'show'])->name('periodic.show');
    Route::get('/periodic-reports/{periodicReport}/edit', [PeriodicReportController::class, 'edit'])->name('periodic.edit');
    Route::put('/periodic-reports/{periodicReport}', [PeriodicReportController::class, 'update'])->name('periodic.update');
    Route::delete('/periodic-reports/{periodicReport}', [PeriodicReportController::class, 'destroy'])->name('periodic.destroy');
    Route::post('/periodic-reports/{periodicReport}/submit', [PeriodicReportController::class, 'submit'])->name('periodic.submit');
    Route::post('/periodic-reports/{periodicReport}/verify', [PeriodicReportController::class, 'verify'])->name('periodic.verify');
    Route::post('/periodic-reports/{periodicReport}/approve', [PeriodicReportController::class, 'approve'])->name('periodic.approve');
    Route::post('/periodic-reports/{periodicReport}/return', [PeriodicReportController::class, 'return'])->name('periodic.return');
    Route::get('/exports/periodic/{type}', [ExportController::class, 'periodic'])
        ->whereIn('type', $periodicTypes)->name('exports.periodic');
    Route::post('/input-dispatches', [InputDispatchController::class, 'store'])->name('dispatches.store');
    Route::delete('/input-dispatches/{dispatch}', [InputDispatchController::class, 'destroy'])->name('dispatches.destroy');

    Route::get('/weekly-plan', [WeeklyPlanReportController::class, 'index'])->name('weekly.index');
    Route::get('/weekly-plan/create', [WeeklyPlanReportController::class, 'create'])->name('weekly.create');
    Route::post('/weekly-plan', [WeeklyPlanReportController::class, 'store'])->name('weekly.store');
    Route::get('/weekly-plan/{weeklyReport}', [WeeklyPlanReportController::class, 'show'])->name('weekly.show');
    Route::get('/weekly-plan/{weeklyReport}/edit', [WeeklyPlanReportController::class, 'edit'])->name('weekly.edit');
    Route::put('/weekly-plan/{weeklyReport}', [WeeklyPlanReportController::class, 'update'])->name('weekly.update');
    Route::delete('/weekly-plan/{weeklyReport}', [WeeklyPlanReportController::class, 'destroy'])->name('weekly.destroy');
    Route::post('/weekly-plan/{weeklyReport}/submit', [WeeklyPlanReportController::class, 'submit'])->name('weekly.submit');
    Route::post('/weekly-plan/{weeklyReport}/approve', [WeeklyPlanReportController::class, 'approve'])->name('weekly.approve');
    Route::post('/weekly-plan/{weeklyReport}/return', [WeeklyPlanReportController::class, 'return'])->name('weekly.return');

    Route::get('/plan', [PlanTargetController::class, 'edit'])->name('plan.edit');
    Route::put('/plan', [PlanTargetController::class, 'update'])->name('plan.update');
    Route::get('/plan/structure', [PlanStructureController::class, 'index'])->name('plan.structure');
    Route::post('/plan/goals', [PlanStructureController::class, 'storeGoal'])->name('plan.goals.store');
    Route::put('/plan/goals/{goal}', [PlanStructureController::class, 'updateGoal'])->name('plan.goals.update');
    Route::post('/plan/activities', [PlanStructureController::class, 'storeActivity'])->name('plan.activities.store');
    Route::put('/plan/activities/{activity}', [PlanStructureController::class, 'updateActivity'])->name('plan.activities.update');

    Route::get('/exports/performance', [ExportController::class, 'performance'])->name('exports.performance');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

    Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
    Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
});
