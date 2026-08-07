<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EvaluationCycleController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\DpcrController;
use App\Http\Controllers\IpcrController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OpcrController;
use App\Http\Controllers\PerformanceTargetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UpcrController;
use App\Http\Controllers\AccomplishmentController;
use App\Http\Controllers\MovController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::middleware('role:Administrator|HR|HRM Office II')->group(function () {
        Route::resource('offices', OfficeController::class)->only(['index']);
        Route::resource('employees', EmployeeController::class)->only(['index']);
    });

    Route::middleware('role:Administrator|HR|HRM Office II')->group(function () {
        Route::resource('employees', EmployeeController::class)->only(['create', 'store']);
    });

    Route::middleware('role:Administrator|HR')->group(function () {
        Route::resource('offices', OfficeController::class)->except(['index', 'show']);
        Route::resource('employees', EmployeeController::class)->only(['edit', 'update', 'destroy']);
        Route::resource('evaluation-cycles', EvaluationCycleController::class)
            ->only(['index', 'create', 'store']);
        Route::patch('evaluation-cycles/{evaluation_cycle}/open', [EvaluationCycleController::class, 'open'])
            ->name('evaluation-cycles.open');
        Route::patch('evaluation-cycles/{evaluation_cycle}/close', [EvaluationCycleController::class, 'close'])
            ->name('evaluation-cycles.close');
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|HRM Office II')->group(function () {
        Route::resource('opcrs', OpcrController::class)->only(['index']);
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head')->group(function () {
        Route::resource('opcrs', OpcrController::class)->only(['create', 'store']);
        Route::patch('opcrs/{opcr}/approve', [OpcrController::class, 'approve'])
            ->name('opcrs.approve');
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|Supervisor|HRM Office II')->group(function () {
        Route::resource('dpcrs', DpcrController::class)->only(['index']);
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|Supervisor')->group(function () {
        Route::resource('dpcrs', DpcrController::class)->only(['create', 'store']);
        Route::patch('dpcrs/{dpcr}/approve', [DpcrController::class, 'approve'])
            ->name('dpcrs.approve');
        Route::resource('evaluations', EvaluationController::class)
            ->only(['index', 'create', 'store', 'show']);
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|Supervisor|Employee|HRM Office II')->group(function () {
        Route::resource('upcrs', UpcrController::class)->only(['index']);
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|Supervisor|Employee')->group(function () {
        Route::resource('upcrs', UpcrController::class)->only(['create', 'store']);
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|Supervisor|Employee|HRM Office II')->group(function () {
        Route::resource('ipcrs', IpcrController::class)->only(['index']);
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|Supervisor|Employee')->group(function () {
        Route::resource('ipcrs', IpcrController::class)->only(['create', 'store']);
        Route::patch('ipcrs/{ipcr}/approve', [IpcrController::class, 'approve'])
            ->name('ipcrs.approve');
        Route::resource('accomplishments', AccomplishmentController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::resource('movs', MovController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::get('movs/{mov}/download', [MovController::class, 'download'])
            ->name('movs.download');
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|HRM Office II')->group(function () {
        Route::resource('opcrs', OpcrController::class)->only(['show']);
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|Supervisor|HRM Office II')->group(function () {
        Route::resource('dpcrs', DpcrController::class)->only(['show']);
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|Supervisor|Employee|HRM Office II')->group(function () {
        Route::resource('upcrs', UpcrController::class)->only(['show']);
        Route::resource('ipcrs', IpcrController::class)->only(['show']);
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|Supervisor|Employee')->group(function () {
        Route::resource('performance-targets', PerformanceTargetController::class)
            ->only(['index']);
    });

    Route::middleware('role:Administrator|HR|Campus Director|Office Head|Supervisor')->group(function () {
        Route::resource('performance-targets', PerformanceTargetController::class)
            ->only(['create', 'store']);
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/reports/{type}/excel', [ReportController::class, 'excel'])->name('reports.excel');
});

require __DIR__.'/auth.php';
