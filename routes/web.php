<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkflowBuilderController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('workflows/{workflow}/builder', [WorkflowBuilderController::class, 'builder'])->name('workflows.builder');
    Route::put('workflows/{workflow}/definition', [WorkflowBuilderController::class, 'update'])->name('workflows.definition.update');
    Route::post('workflows/{workflow}/validate', [WorkflowBuilderController::class, 'validateDefinition'])->name('workflows.validate');
    Route::post('workflows/{workflow}/publish', [WorkflowBuilderController::class, 'publish'])->name('workflows.publish');
    Route::get('catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('catalog/{workflow}', [CatalogController::class, 'show'])->name('catalog.show');
    Route::get('requests', [RequestController::class, 'index'])->name('requests.index');
    Route::post('requests', [RequestController::class, 'store'])->name('requests.store');
    Route::get('requests/{instance}', [RequestController::class, 'show'])->name('requests.show');
    Route::post('requests/{instance}/cancel', [RequestController::class, 'cancel'])->name('requests.cancel');
    Route::get('files/{file}', [RequestController::class, 'download'])->name('files.download');
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/{task}/decision', [ApprovalController::class, 'decision'])->name('approvals.decision');
    Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('reports', [MonitoringController::class, 'reports'])->name('reports.index');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::resource('workflows', WorkflowController::class)->except('show');
    Route::post('workflows/{workflow}/archive', [WorkflowController::class, 'archive'])->name('workflows.archive');
    Route::post('workflows/{workflow}/duplicate', [WorkflowController::class, 'duplicate'])->name('workflows.duplicate');
    Route::resource('users', UserController::class)->except('show');
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
