<?php

use App\Http\Controllers\AccomplishmentController;
use App\Http\Controllers\CommitmentPlanningController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DpcrController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\EvaluationCycleController;
use App\Http\Controllers\HrHeadRatingController;
use App\Http\Controllers\IpcrController;
use App\Http\Controllers\MovController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OfficeTargetController;
use App\Http\Controllers\OpcrController;
use App\Http\Controllers\PerformanceTargetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportAttachmentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportWorkflowController;
use App\Http\Controllers\StrategicGoalController;
use App\Http\Controllers\StrategicObjectiveController;
use App\Http\Controllers\TargetDocumentController;
use App\Http\Controllers\TargetMovController;
use App\Http\Controllers\UpcrController;
use Illuminate\Support\Facades\Route;

Route::pattern('office_target', '[0-9]+');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('planning', [CommitmentPlanningController::class, 'index'])->name('planning.index');
    Route::post('planning', [CommitmentPlanningController::class, 'store'])->name('planning.store');
    Route::post('planning/organization/{office}', [CommitmentPlanningController::class, 'organization'])->name('planning.organization');
    Route::post('planning/duties/{person}', [CommitmentPlanningController::class, 'duties'])->name('planning.duties');
    Route::post('planning/activate/{cycle}', [CommitmentPlanningController::class, 'activate'])->name('planning.activate');
    Route::post('planning/proposals', [CommitmentPlanningController::class, 'proposal'])->name('planning.proposal');
    Route::post('planning/proposals/{proposal}/review', [CommitmentPlanningController::class, 'reviewProposal'])->name('planning.proposal-review');
    Route::post('planning/proposals/{proposal}/resubmit', [CommitmentPlanningController::class, 'resubmitProposal'])->name('planning.proposal-resubmit');
    Route::post('planning/targets/{target}/rate', [CommitmentPlanningController::class, 'rate'])->name('planning.rate');
    Route::get('planning/{plan}/approval/{event}/download', [CommitmentPlanningController::class, 'approvalEvidence'])->name('planning.approval-download');
    Route::post('planning/targets/{target}/update', [CommitmentPlanningController::class, 'updateTarget'])->name('planning.target-update');
    Route::post('planning/targets/{target}/eligibility', [CommitmentPlanningController::class, 'eligibility'])->name('planning.eligibility');
    Route::post('planning/targets/{target}/driver', [CommitmentPlanningController::class, 'driver'])->name('planning.driver');
    Route::post('planning/targets/{target}/outputs', [CommitmentPlanningController::class, 'output'])->name('planning.output');
    Route::post('planning/outputs/{output}/validate', [CommitmentPlanningController::class, 'validateOutput'])->name('planning.output-validate');
    Route::post('planning/outputs/{output}/evidence', [CommitmentPlanningController::class, 'supplement'])->name('planning.output-evidence');
    Route::post('planning/outputs/{output}/contribution', [CommitmentPlanningController::class, 'contribution'])->name('planning.contribution');
    Route::get('planning/outputs/{output}/download', [CommitmentPlanningController::class, 'download'])->name('planning.output-download');
    Route::get('planning/{plan}', [CommitmentPlanningController::class, 'show'])->name('planning.show');
    Route::post('planning/{plan}/targets', [CommitmentPlanningController::class, 'target'])->name('planning.target');
    Route::post('planning/{plan}/import/{draft}', [CommitmentPlanningController::class, 'import'])->name('planning.import');
    Route::post('planning/{plan}/suggest', [CommitmentPlanningController::class, 'suggest'])->middleware('throttle:5,1')->name('planning.suggest');
    Route::post('planning/{plan}/actions/{action}', [CommitmentPlanningController::class, 'transition'])->name('planning.transition');
    Route::get('planning/{plan}/{format}', [CommitmentPlanningController::class, 'export'])->where('format', 'pdf|excel')->name('planning.export');
    Route::middleware('role:Administrator|Campus Director')->group(function () {
        Route::get('target-documents', [TargetDocumentController::class, 'index'])->name('target-documents.index');
        Route::post('target-documents', [TargetDocumentController::class, 'store'])->middleware('throttle:10,1')->name('target-documents.store');
        Route::get('target-documents/{document}', [TargetDocumentController::class, 'show'])->name('target-documents.show');
        Route::get('target-documents/{document}/download', [TargetDocumentController::class, 'download'])->name('target-documents.download');
        Route::post('target-documents/{document}/analyze', [TargetDocumentController::class, 'analyze'])->middleware('throttle:5,1')->name('target-documents.analyze');
        Route::get('target-drafts/{draft}', [TargetDocumentController::class, 'review'])->name('target-drafts.review');
        Route::post('target-drafts/{draft}/assign', [TargetDocumentController::class, 'assign'])->middleware('throttle:10,1')->name('target-drafts.assign');
    });
    Route::get('ratings', HrHeadRatingController::class)->middleware('role:HR|HR Head')->name('ratings.index');
    Route::get('ratings/pdf', [HrHeadRatingController::class, 'pdf'])->middleware('role:HR|HR Head')->name('ratings.pdf');
    Route::post('report-attachments/{type}/{report}', [ReportAttachmentController::class, 'store'])->middleware('role:Supervisor')->name('report-attachments.store');
    Route::get('report-attachments/{attachment}/download', [ReportAttachmentController::class, 'download'])->middleware('role:Supervisor')->name('report-attachments.download');
    Route::get('report-workflow/{type}/{report}/edit', [ReportWorkflowController::class, 'edit'])->name('report-workflow.edit');
    Route::put('report-workflow/{type}/{report}', [ReportWorkflowController::class, 'update'])->name('report-workflow.update');
    Route::patch('report-workflow/{type}/{report}/submit', [ReportWorkflowController::class, 'submit'])->name('report-workflow.submit');
    Route::patch('report-workflow/{type}/{report}/return', [ReportWorkflowController::class, 'returnForCorrection'])->name('report-workflow.return');
    Route::patch('report-workflow/{type}/{report}/approve', [ReportWorkflowController::class, 'approve'])->name('report-workflow.approve');
    Route::post('office-targets/{officeTarget}/employees', [OfficeTargetController::class, 'assignEmployees'])->middleware('role:Supervisor|HR Head')->name('office-targets.assign-employees');
    Route::post('office-targets/{officeTarget}/take', [OfficeTargetController::class, 'takeTarget'])->middleware('role:Supervisor|HR Head')->name('office-targets.take');
    Route::patch('office-targets/{officeTarget}/accept', [OfficeTargetController::class, 'acceptTarget'])->middleware('role:Employee')->name('office-targets.accept');
    Route::post('office-targets/{officeTarget}/movs', [TargetMovController::class, 'store'])->middleware('role:Employee|Supervisor|HR Head')->name('target-movs.store');
    Route::patch('office-targets/{officeTarget}/complete', [TargetMovController::class, 'complete'])->middleware('role:Employee|Supervisor|HR Head')->name('target-movs.complete');
    Route::get('target-movs/{targetMov}/download', [TargetMovController::class, 'download'])->name('target-movs.download');
    Route::get('target-movs/{targetMov}/preview', [TargetMovController::class, 'preview'])->name('target-movs.preview');
    Route::middleware('role:Administrator|Campus Director|Supervisor|HR Head|Employee')->group(function () {
        Route::resource('office-targets', OfficeTargetController::class)->only(['index', 'show']);
        Route::get('office-targets/{officeTarget}/download', [OfficeTargetController::class, 'download'])->name('office-targets.download');
    });
    Route::resource('office-targets', OfficeTargetController::class)->only(['create', 'store', 'edit', 'update'])->middleware('role:Administrator|Campus Director');

    Route::middleware('role:Administrator|Campus Director')->group(function () {
        Route::resource('strategic-goals', StrategicGoalController::class)->except(['show']);
        Route::get('strategic-goals/{strategicGoal}/objectives/create', [StrategicObjectiveController::class, 'create'])->name('strategic-objectives.create');
        Route::post('strategic-goals/{strategicGoal}/objectives', [StrategicObjectiveController::class, 'store'])->name('strategic-objectives.store');
        Route::get('strategic-objectives/{strategicObjective}/edit', [StrategicObjectiveController::class, 'edit'])->name('strategic-objectives.edit');
        Route::put('strategic-objectives/{strategicObjective}', [StrategicObjectiveController::class, 'update'])->name('strategic-objectives.update');
        Route::delete('strategic-objectives/{strategicObjective}', [StrategicObjectiveController::class, 'destroy'])->name('strategic-objectives.destroy');
    });

    Route::middleware('role:Administrator|HR|HR Head|HRM Office II')->group(function () {
        Route::resource('offices', OfficeController::class)->only(['index']);
        Route::resource('employees', EmployeeController::class)->only(['index']);
    });

    Route::middleware('role:Administrator|HR|HR Head|HRM Office II')->group(function () {
        Route::resource('employees', EmployeeController::class)->only(['create', 'store']);
    });

    Route::middleware('role:Administrator|HR|HR Head')->group(function () {
        Route::resource('offices', OfficeController::class)->except(['index', 'show']);
        Route::resource('employees', EmployeeController::class)->only(['edit', 'update', 'destroy']);
        Route::resource('evaluation-cycles', EvaluationCycleController::class)
            ->only(['index', 'create', 'store']);
        Route::patch('evaluation-cycles/{evaluation_cycle}/open', [EvaluationCycleController::class, 'open'])
            ->name('evaluation-cycles.open');
        Route::patch('evaluation-cycles/{evaluation_cycle}/close', [EvaluationCycleController::class, 'close'])
            ->name('evaluation-cycles.close');
    });

    Route::middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor|HRM Office II')->group(function () {
        Route::resource('opcrs', OpcrController::class)->only(['index']);
    });

    Route::middleware('role:Campus Director')->group(function () {
        Route::resource('opcrs', OpcrController::class)->only(['create', 'store']);
    });

    Route::middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor|HRM Office II')->group(function () {
        Route::resource('dpcrs', DpcrController::class)->only(['index']);
    });

    Route::middleware('role:Supervisor|HR Head')->group(function () {
        Route::resource('dpcrs', DpcrController::class)->only(['create', 'store']);
    });
    Route::middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor')->group(function () {
        Route::resource('evaluations', EvaluationController::class)
            ->only(['index', 'create', 'store', 'show']);
    });

    Route::middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor|Employee|HRM Office II')->group(function () {
        Route::resource('upcrs', UpcrController::class)->only(['index'])->middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor|HRM Office II');
    });

    Route::middleware('role:Supervisor')->group(function () {
        Route::resource('upcrs', UpcrController::class)->only(['create', 'store']);
    });

    Route::middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor|Employee|HRM Office II')->group(function () {
        Route::resource('ipcrs', IpcrController::class)->only(['index']);
    });

    // Every personnel role prepares its own IPCR; office assignment scopes the available UPCRs.
    Route::resource('ipcrs', IpcrController::class)->only(['create', 'store']);
    Route::middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor|Employee')->group(function () {
        Route::resource('accomplishments', AccomplishmentController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::resource('movs', MovController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::get('movs/{mov}/download', [MovController::class, 'download'])
            ->name('movs.download');
    });

    Route::middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor|HRM Office II')->group(function () {
        Route::resource('opcrs', OpcrController::class)->only(['show']);
    });

    Route::middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor|HRM Office II')->group(function () {
        Route::resource('dpcrs', DpcrController::class)->only(['show']);
    });

    Route::middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor|Employee|HRM Office II')->group(function () {
        Route::resource('upcrs', UpcrController::class)->only(['show'])->middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor|HRM Office II');
        Route::resource('ipcrs', IpcrController::class)->only(['show']);
    });

    Route::middleware('role:Administrator|HR|HR Head|Campus Director|Supervisor|Employee')->group(function () {
        Route::resource('performance-targets', PerformanceTargetController::class)
            ->only(['index']);
    });

    Route::middleware('role:Administrator|Campus Director')->group(function () {
        Route::resource('performance-targets', PerformanceTargetController::class)
            ->only(['create']);
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/finished/{type}/{report}', [ReportController::class, 'finished'])->name('reports.finished');
    Route::get('/reports/finished/{type}/{report}/pdf', [ReportController::class, 'finishedPdf'])->name('reports.finished.pdf');
    Route::get('/reports/finished/{type}/{report}/excel', [ReportController::class, 'finishedExcel'])->name('reports.finished.excel');
    Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{type}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/reports/{type}/excel', [ReportController::class, 'excel'])->name('reports.excel');
});
