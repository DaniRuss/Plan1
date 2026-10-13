<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

// Redirect root to dashboard if authenticated, otherwise to login
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::get('/login/session-token', [LoginController::class, 'sessionToken'])->name('login.session-token');
    Route::post('/login', [LoginController::class, 'login']);
});

// Logout
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/first-password', [App\Http\Controllers\ProfileController::class, 'firstPasswordForm'])->name('password.first.edit');
    Route::put('/first-password', [App\Http\Controllers\ProfileController::class, 'updateFirstPassword'])->name('password.first.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('department-plan-files', [App\Http\Controllers\DepartmentPlanSubmissionController::class, 'index'])->name('plan-submissions.index');
    Route::post('department-plan-files', [App\Http\Controllers\DepartmentPlanSubmissionController::class, 'store'])->name('plan-submissions.store');
    Route::get('department-plan-files/{submission}/download', [App\Http\Controllers\DepartmentPlanSubmissionController::class, 'download'])->name('plan-submissions.download');
    Route::get('/performance-comparison', [DashboardController::class, 'performanceComparison'])->name('performance.comparison')->middleware('can:view_performance');

    // Departments
    Route::resource('departments', DepartmentController::class)->middleware('can:manage_departments');

    // Strategic Objectives
    Route::get('objectives/template/download', [App\Http\Controllers\StrategicObjectiveController::class, 'downloadTemplate'])->name('objectives.download-template')->middleware('can:import_objectives');
    Route::post('objectives/{objective}/reporting-frequency', [App\Http\Controllers\ObjectiveReportingFrequencyController::class, 'requestChange'])->name('objectives.frequency.request');
    Route::post('objectives/{objective}/reporting-frequency/decision', [App\Http\Controllers\ObjectiveReportingFrequencyController::class, 'decide'])->name('objectives.frequency.decide');
    Route::get('objectives/import', [App\Http\Controllers\StrategicObjectiveController::class, 'importForm'])->name('objectives.import.form')->middleware('can:import_objectives');
    Route::post('objectives/import', [App\Http\Controllers\ObjectiveWorkbookImportController::class, 'upload'])->name('objectives.import')->middleware('can:import_objectives');
    Route::get('objectives/import/review/{token}', [App\Http\Controllers\ObjectiveWorkbookImportController::class, 'review'])->name('objectives.import.review')->middleware('can:import_objectives');
    Route::post('objectives/import/review/{token}', [App\Http\Controllers\ObjectiveWorkbookImportController::class, 'commit'])->name('objectives.import.commit')->middleware('can:import_objectives');
    Route::get('objectives/download/approved', [App\Http\Controllers\EdrPlanController::class, 'downloadObjectives'])->name('objectives.download-approved')->middleware('can:view_objectives');
    Route::get('objectives/edr-plan/narrative', [App\Http\Controllers\EdrPlanController::class, 'editOrganizationNarrative'])->name('objectives.plan-narrative.edit')->middleware('can:view_objectives');
    Route::get('objectives/edr-plan/narrative/word', [App\Http\Controllers\EdrPlanController::class, 'downloadOrganizationNarrativeWord'])->name('objectives.plan-narrative.word')->middleware('can:view_objectives');
    Route::get('objectives/edr-plan/narrative/attachment', [App\Http\Controllers\EdrPlanController::class, 'downloadNarrativeAttachment'])->name('objectives.plan-narrative.attachment')->middleware('can:view_objectives');
    Route::put('objectives/edr-plan/narrative', [App\Http\Controllers\EdrPlanController::class, 'updateOrganizationNarrative'])->name('objectives.plan-narrative.update')->middleware('can:view_objectives');
    Route::get('objectives/{objective}/download', [App\Http\Controllers\EdrPlanController::class, 'downloadObjective'])->name('objectives.download')->middleware('can:view_objectives');
    Route::get('api/objectives/next-code/{parentId?}', [App\Http\Controllers\StrategicObjectiveController::class, 'getNextCode'])->middleware('can:manage_objectives');
    Route::get('objectives', [App\Http\Controllers\StrategicObjectiveController::class, 'index'])->name('objectives.index')->middleware('can:view_objectives');
    Route::get('period-weights', [App\Http\Controllers\PeriodWeightDistributionController::class, 'edit'])->name('period-weights.edit')->middleware('can:view_objectives');
    Route::post('period-weights', [App\Http\Controllers\PeriodWeightDistributionController::class, 'store'])->name('period-weights.store')->middleware('can:view_objectives');
    Route::post('period-weights/workflow', [App\Http\Controllers\PeriodWeightDistributionController::class, 'transition'])->name('period-weights.transition')->middleware('can:view_objectives');
    Route::get('objectives/create', [App\Http\Controllers\StrategicObjectiveController::class, 'create'])->name('objectives.create')->middleware('can:manage_objectives');
    Route::post('objectives', [App\Http\Controllers\StrategicObjectiveController::class, 'store'])->name('objectives.store')->middleware('can:manage_objectives');
    Route::get('objectives/{objective}', [App\Http\Controllers\StrategicObjectiveController::class, 'show'])->name('objectives.show')->middleware('can:view_objectives');
    Route::get('objectives/{objective}/edit', [App\Http\Controllers\StrategicObjectiveController::class, 'edit'])->name('objectives.edit')->middleware('can:view_objectives');
    Route::put('objectives/{objective}', [App\Http\Controllers\StrategicObjectiveController::class, 'update'])->name('objectives.update')->middleware('can:view_objectives');
    Route::delete('objectives/{objective}', [App\Http\Controllers\StrategicObjectiveController::class, 'destroy'])->name('objectives.destroy')->middleware('can:manage_objectives');
    
    // Objective Workflow Routes
    Route::post('objectives/{objective}/submit', [App\Http\Controllers\StrategicObjectiveController::class, 'submit'])->name('objectives.submit')->middleware('can:manage_objectives');
    Route::post('objectives/{objective}/directorate-forward', [App\Http\Controllers\StrategicObjectiveController::class, 'directorateForward'])->name('objectives.directorate-forward');
    Route::post('objectives/{objective}/directorate-return', [App\Http\Controllers\StrategicObjectiveController::class, 'directorateReturn'])->name('objectives.directorate-return');
    Route::post('objectives/{objective}/recall-request', [App\Http\Controllers\StrategicObjectiveController::class, 'requestRecall'])->name('objectives.recall-request')->middleware('can:manage_objectives');
    Route::post('objectives/{objective}/recall-approve', [App\Http\Controllers\StrategicObjectiveController::class, 'approveRecall'])->name('objectives.recall-approve');
    Route::post('objectives/{objective}/recall-reject', [App\Http\Controllers\StrategicObjectiveController::class, 'rejectRecall'])->name('objectives.recall-reject');
    Route::post('objectives/{objective}/approve', [App\Http\Controllers\StrategicObjectiveController::class, 'approve'])->name('objectives.approve');
    Route::post('objectives/{objective}/reject', [App\Http\Controllers\StrategicObjectiveController::class, 'reject'])->name('objectives.reject');

    // Plans
    Route::get('plans/{plan}/download', [App\Http\Controllers\EdrPlanController::class, 'downloadPlan'])->name('plans.download')->middleware('can:view_plans');
    Route::get('plans/{plan}/objectives/{objective}/download', [App\Http\Controllers\EdrPlanController::class, 'downloadPlanObjective'])->name('plans.objectives.download')->middleware('can:view_plans');
    Route::post('plans/{plan}/items', [App\Http\Controllers\PlanController::class, 'updateItems'])->name('plans.updateItems')->middleware('can:edit_plan');
    Route::post('plans/{plan}/submit', [App\Http\Controllers\PlanController::class, 'submit'])->name('plans.submit')->middleware('can:submit_plan');
    Route::post('plans/{plan}/approve', [App\Http\Controllers\PlanController::class, 'approve'])->name('plans.approve')->middleware('can:approve_plan');
    Route::post('plans/{plan}/reject', [App\Http\Controllers\PlanController::class, 'reject'])->name('plans.reject')->middleware('can:approve_plan');
    Route::resource('plans', App\Http\Controllers\PlanController::class)->middleware('can:view_plans');

    // Assignments Acceptance Workflow
    Route::get('assignments', [App\Http\Controllers\PlanAssignmentController::class, 'index'])->name('assignments.index')->middleware('can:view_plans');
    Route::post('assignments/{assignment}/accept', [App\Http\Controllers\PlanAssignmentController::class, 'accept'])->name('assignments.accept');
    Route::post('assignments/{assignment}/reject', [App\Http\Controllers\PlanAssignmentController::class, 'reject'])->name('assignments.reject');
    Route::post('assignments/{assignment}/chief-approve', [App\Http\Controllers\PlanAssignmentController::class, 'chiefApprove'])->name('assignments.chief-approve');
    Route::post('assignments/{assignment}/chief-return', [App\Http\Controllers\PlanAssignmentController::class, 'chiefReturn'])->name('assignments.chief-return');
    Route::post('assignments/{assignment}/directorate-approve', [App\Http\Controllers\PlanAssignmentController::class, 'directorateApprove'])->name('assignments.directorate-approve');
    Route::post('assignments/{assignment}/directorate-return', [App\Http\Controllers\PlanAssignmentController::class, 'directorateReturn'])->name('assignments.directorate-return');
    Route::post('assignments/{assignment}/head-office-resend', [App\Http\Controllers\PlanAssignmentController::class, 'headOfficeResend'])->name('assignments.head-office-resend');
    Route::get('assignments/{assignment}/supplementary/create', [App\Http\Controllers\PlanAssignmentController::class, 'supplementaryCreate'])->name('assignments.supplementary.create');
    Route::post('assignments/{assignment}/supplementary', [App\Http\Controllers\PlanAssignmentController::class, 'supplementaryStore'])->name('assignments.supplementary.store');

    // Performance Tracking
    Route::get('performance', [App\Http\Controllers\PerformanceController::class, 'index'])->name('performance.index')->middleware('can:view_performance');
    Route::get('performance/{plan}/{period}', [App\Http\Controllers\PerformanceController::class, 'edit'])->name('performance.edit')->middleware('can:enter_performance');
    Route::put('performance/{plan}/{period}', [App\Http\Controllers\PerformanceController::class, 'update'])->name('performance.update')->middleware('can:enter_performance');
    Route::get('performance/{plan}/{period}/report', [App\Http\Controllers\PerformanceController::class, 'report'])->name('performance.report')->middleware('can:view_performance');
    Route::get('performance-attachments/{attachment}/download', [App\Http\Controllers\PerformanceAttachmentController::class, 'download'])->name('performance-attachments.download')->middleware('can:view_performance');

    // Weekly Performance Tracking (New Module)
    // Reviewers and final approvers need to open the entry page read-only before
    // using its report/workflow actions. Only the POST route requires entry permission.
    Route::get('weekly/entry/{year}/{month}/{week}', [App\Http\Controllers\WeeklyPerformanceController::class, 'entry'])->name('weekly.entry')->middleware(['can:view_performance', 'can:view_weekly_performance']);
    Route::post('weekly/entry', [App\Http\Controllers\WeeklyPerformanceController::class, 'store'])->name('weekly.store')->middleware(['can:enter_performance', 'can:view_weekly_performance']);
    Route::post('weekly/workflow/{year}/{month}/{week}', [App\Http\Controllers\WeeklyPerformanceController::class, 'transition'])->name('weekly.workflow')->middleware(['can:view_performance', 'can:view_weekly_operational_detail']);
    Route::get('weekly/dashboard', [App\Http\Controllers\WeeklyPerformanceController::class, 'dashboard'])->name('weekly.dashboard')->middleware(['can:view_performance', 'can:view_weekly_performance']);
    Route::get('weekly/report/{year}/{month}/{week}', [App\Http\Controllers\WeeklyPerformanceController::class, 'report'])->name('weekly.report')->middleware(['can:view_performance', 'can:view_weekly_operational_detail']);
    Route::get('weekly/export/{year}/{month}/{week}', [App\Http\Controllers\WeeklyPerformanceController::class, 'exportWeekly'])->name('weekly.export')->middleware(['can:view_performance', 'can:view_weekly_operational_detail']);

    // Daily Performance Tracking (New Module)
    Route::get('daily/dashboard', [App\Http\Controllers\DailyPerformanceController::class, 'dashboard'])->name('daily.dashboard')->middleware(['can:view_performance', 'can:view_daily_performance']);
    Route::get('daily/entry/{year}/{month}/{week}', [App\Http\Controllers\DailyPerformanceController::class, 'entry'])->name('daily.entry')->middleware(['can:view_performance', 'can:view_daily_performance']);
    Route::post('daily/store', [App\Http\Controllers\DailyPerformanceController::class, 'store'])->name('daily.store')->middleware(['can:enter_performance', 'can:view_daily_performance']);

    // Monthly Aggregator (New Module)
    Route::get('monthly/dashboard', [App\Http\Controllers\MonthlyAggregationController::class, 'index'])->name('monthly.dashboard')->middleware(['can:view_performance', 'can:view_monthly_performance']);
    Route::get('monthly/report/{year}/{month}', [App\Http\Controllers\MonthlyAggregationController::class, 'report'])->name('monthly.report')->middleware(['can:view_performance', 'can:view_monthly_performance']);
    Route::get('monthly/export/{year}/{month}', [App\Http\Controllers\MonthlyAggregationController::class, 'export'])->name('monthly.export')->middleware(['can:view_performance', 'can:view_monthly_performance']);
    Route::post('monthly/certify', [App\Http\Controllers\MonthlyAggregationController::class, 'certify'])->name('monthly.certify')->middleware('can:view_monthly_performance');
    Route::post('monthly/reopen', [App\Http\Controllers\MonthlyAggregationController::class, 'reopen'])->name('monthly.reopen')->middleware('can:view_monthly_performance');
    Route::post('monthly/workflow/{year}/{month}', [App\Http\Controllers\MonthlyAggregationController::class, 'transition'])->name('monthly.workflow')->middleware(['can:view_performance', 'can:view_monthly_performance']);
    // Manual monthly entry for monthly-reporting departments
    Route::get('monthly/entry/{year}/{month}', [App\Http\Controllers\MonthlyAggregationController::class, 'entryForm'])->name('monthly.entry.form')->middleware(['can:enter_performance', 'can:view_monthly_performance']);
    Route::post('monthly/entry/{year}/{month}', [App\Http\Controllers\MonthlyAggregationController::class, 'entryStore'])->name('monthly.entry.store')->middleware(['can:enter_performance', 'can:view_monthly_performance']);

    // Department Scorecard (New Module)
    Route::get('scorecard/leaderboard', [App\Http\Controllers\ScorecardController::class, 'leaderboard'])->name('scorecard.leaderboard')->middleware('can:view_scorecard');

    // Data Validation & Quality (New Module)
    Route::get('quality/console', [App\Http\Controllers\ValidationConsoleController::class, 'index'])->name('quality.console')->middleware('can:manage_quality');

    // Employee Management
    Route::get('employees/template/download', [App\Http\Controllers\EmployeeController::class, 'downloadTemplate'])->name('employees.download-template')->middleware('can:import_employees');
    Route::get('employees/import', [App\Http\Controllers\EmployeeController::class, 'importForm'])->name('employees.import.form')->middleware('can:import_employees');
    Route::post('employees/import', [App\Http\Controllers\EmployeeController::class, 'import'])->name('employees.import')->middleware('can:import_employees');
    Route::post('employees/{employee}/create-account', [App\Http\Controllers\EmployeeController::class, 'createAccount'])->name('employees.create-account')->middleware('can:create_employee_accounts');
    Route::resource('employees', App\Http\Controllers\EmployeeController::class)->middleware('can:view_employees');
    
    // API Routes for Smart Onboarding
    Route::get('api/departments/{id}/children', [App\Http\Controllers\DepartmentController::class, 'getChildren'])->middleware('can:access_department_lookup');
    Route::get('api/employees/next-number/{departmentId}', [App\Http\Controllers\EmployeeController::class, 'getNextNumber'])->middleware('can:manage_employees');

    // Profile
    Route::get('profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile/info', [App\Http\Controllers\ProfileController::class, 'updateInfo'])->name('profile.info.update');
    Route::put('profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Notifications
    Route::get('notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');

    // Overall Master Performance Report (New Module)
    Route::get('reports/master', [App\Http\Controllers\MasterReportController::class, 'index'])->name('reports.master')->middleware('can:view_performance');
    Route::get('reports/master/export/excel', [App\Http\Controllers\MasterReportController::class, 'exportExcel'])->name('reports.master.excel')->middleware('can:view_performance');
    Route::get('reports/master/export/pdf', [App\Http\Controllers\MasterReportController::class, 'exportPdf'])->name('reports.master.pdf')->middleware('can:view_performance');

    // EDR Department Feedback and Evaluation
    Route::get('feedbacks', [App\Http\Controllers\EdrFeedbackController::class, 'index'])->name('feedbacks.index')->middleware('can:view_feedbacks');
    Route::get('feedbacks/create', [App\Http\Controllers\EdrFeedbackController::class, 'create'])->name('feedbacks.create')->middleware('can:view_feedbacks');
    Route::post('feedbacks', [App\Http\Controllers\EdrFeedbackController::class, 'store'])->name('feedbacks.store')->middleware('can:view_feedbacks');
    Route::post('feedbacks/{feedback}/submit', [App\Http\Controllers\EdrFeedbackController::class, 'submit'])->name('feedbacks.submit')->middleware('can:view_feedbacks');
    Route::post('feedbacks/{feedback}/forward-to-head', [App\Http\Controllers\EdrFeedbackController::class, 'forwardToHead'])->name('feedbacks.forward-head')->middleware('can:view_feedbacks');
    Route::post('feedbacks/{feedback}/finalize', [App\Http\Controllers\EdrFeedbackController::class, 'finalize'])->name('feedbacks.finalize')->middleware('can:view_feedbacks');
    Route::get('feedbacks/{feedback}', [App\Http\Controllers\EdrFeedbackController::class, 'show'])->name('feedbacks.show')->middleware('can:view_feedbacks');
    Route::get('feedbacks/{feedback}/edit', [App\Http\Controllers\EdrFeedbackController::class, 'edit'])->name('feedbacks.edit')->middleware('can:view_feedbacks');
    Route::put('feedbacks/{feedback}', [App\Http\Controllers\EdrFeedbackController::class, 'update'])->name('feedbacks.update')->middleware('can:view_feedbacks');
    Route::get('feedbacks/{feedback}/pdf', [App\Http\Controllers\EdrFeedbackController::class, 'pdf'])->name('feedbacks.pdf')->middleware('can:view_feedbacks');

    // Performance Reports (EDR)
    Route::get('reports/preview', [App\Http\Controllers\EdrReportController::class, 'preview'])->name('reports.preview')->middleware('can:view_reports');
    // KPI Dashboard MUST be before the resource route to avoid {report} wildcard capturing it
    Route::get('reports/kpi-dashboard', [App\Http\Controllers\EdrReportController::class, 'kpiDashboard'])->name('reports.kpi-dashboard')->middleware('can:view_performance');
    Route::post('reports/{report}/submit', [App\Http\Controllers\EdrReportController::class, 'submit'])->name('reports.submit')->middleware('can:submit_report');
    Route::post('reports/{report}/forward-to-head', [App\Http\Controllers\EdrReportController::class, 'forwardToHead'])->name('reports.forward-head')->middleware('can:view_reports');
    Route::post('reports/{report}/approve', [App\Http\Controllers\EdrReportController::class, 'approve'])->name('reports.approve')->middleware('can:approve_report');
    Route::get('reports/{report}/pdf', [App\Http\Controllers\EdrReportController::class, 'exportPdf'])->name('reports.pdf')->middleware('can:export_reports');
    Route::get('reports/{report}/excel', [App\Http\Controllers\EdrReportController::class, 'exportExcel'])->name('reports.excel')->middleware('can:export_reports');
    Route::get('reports/{report}/csv', [App\Http\Controllers\EdrReportController::class, 'exportCsv'])->name('reports.csv')->middleware('can:export_reports');
    Route::resource('reports', App\Http\Controllers\EdrReportController::class)->middleware('can:view_reports');

    // Employee Performance Reports
    Route::get('employees/{employee}/performance', [App\Http\Controllers\EmployeePerformanceController::class, 'show'])->name('reports.employee.show')->middleware('can:view_employee_performance');
    Route::get('employees/{employee}/performance/pdf', [App\Http\Controllers\EmployeePerformanceController::class, 'exportPdf'])->name('reports.employee.pdf')->middleware('can:export_employee_performance');
    Route::get('employees/{employee}/performance/excel', [App\Http\Controllers\EmployeePerformanceController::class, 'exportExcel'])->name('reports.employee.excel')->middleware('can:export_employee_performance');

    // Audit Logs
    Route::get('audit-logs', [App\Http\Controllers\AuditController::class, 'index'])->name('audit.index')->middleware('can:view_audit_logs');

    // System Settings
    Route::group(['middleware' => 'can:manage_settings'], function () {
        Route::get('settings', [App\Http\Controllers\SettingController::class, 'index'])->name('settings.index');
        Route::post('settings', [App\Http\Controllers\SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/reset', [App\Http\Controllers\SettingController::class, 'reset'])->name('settings.reset');
    });

    // Users
    Route::post('users/{user}/approve', [App\Http\Controllers\UserController::class, 'approve'])->name('users.approve')->middleware('can:manage_users');
    Route::resource('users', App\Http\Controllers\UserController::class)->middleware('can:manage_users');

    // Cargo Tracking & Logistics
    Route::get('cargo/dashboard', [App\Http\Controllers\CargoController::class, 'dashboard'])->name('cargo.dashboard')->middleware('can:system_admin');
    Route::post('cargo/{cargo}/assign-train', [App\Http\Controllers\CargoController::class, 'assignTrain'])->name('cargo.assign-train')->middleware('can:system_admin');
    Route::post('cargo/{cargo}/transfer', [App\Http\Controllers\CargoController::class, 'transfer'])->name('cargo.transfer')->middleware('can:system_admin');
    Route::post('cargo/{cargo}/accept', [App\Http\Controllers\CargoController::class, 'accept'])->name('cargo.accept')->middleware('can:system_admin');
    Route::post('cargo/{cargo}/dispatch', [App\Http\Controllers\CargoController::class, 'dispatch'])->name('cargo.dispatch')->middleware('can:system_admin');
    Route::resource('cargo', App\Http\Controllers\CargoController::class)->middleware('can:system_admin');

    Route::post('trains/{journey}/depart', [App\Http\Controllers\TrainJourneyController::class, 'depart'])->name('trains.depart')->middleware('can:system_admin');
    Route::post('trains/{journey}/arrive', [App\Http\Controllers\TrainJourneyController::class, 'arrive'])->name('trains.arrive')->middleware('can:system_admin');
    Route::resource('trains', App\Http\Controllers\TrainJourneyController::class)->middleware('can:system_admin');

    // ── Import Tracking V2 (PDF-Aligned Workflow) ─────────────────────────
    Route::prefix('imports')->name('imports.')->middleware('can:system_admin')->group(function () {
        Route::get('dashboard', [App\Http\Controllers\ImportController::class, 'dashboard'])->name('dashboard');
        Route::get('/', [App\Http\Controllers\ImportController::class, 'index'])->name('index');
        Route::get('create', [App\Http\Controllers\ImportController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\ImportController::class, 'store'])->name('store');
        Route::get('{import}', [App\Http\Controllers\ImportController::class, 'show'])->name('show');
        Route::post('{import}/validate-ecd', [App\Http\Controllers\ImportController::class, 'validateECD'])->name('validate-ecd');
        Route::post('{import}/release-doc', [App\Http\Controllers\ImportController::class, 'attachReleaseDoc'])->name('release-doc');
        Route::post('{import}/assign-wagon', [App\Http\Controllers\ImportController::class, 'assignWagon'])->name('assign-wagon');
        Route::post('{import}/transfer-yard', [App\Http\Controllers\ImportController::class, 'transferToYard'])->name('transfer-yard');
        Route::post('{import}/transit-docs', [App\Http\Controllers\ImportController::class, 'submitTransitDocs'])->name('transit-docs');
        Route::post('{import}/approve-operation', [App\Http\Controllers\ImportController::class, 'approveOperation'])->name('approve-operation');
        Route::post('{import}/mark-loaded', [App\Http\Controllers\ImportController::class, 'markLoaded'])->name('mark-loaded');
        Route::post('{import}/dispatch-train', [App\Http\Controllers\ImportController::class, 'dispatchTrain'])->name('dispatch-train');
        Route::post('{import}/upload-document', [App\Http\Controllers\ImportController::class, 'uploadDocument'])->name('upload-document');
        Route::post('{import}/mark-payment', [App\Http\Controllers\ImportController::class, 'markPayment'])->name('mark-payment');
    });

    // Task Management
    Route::get('tasks/my', [App\Http\Controllers\TaskController::class, 'myTasks'])->name('tasks.my')->middleware('can:manage_own_tasks');
    Route::post('tasks/{task}/report', [App\Http\Controllers\TaskController::class, 'report'])->name('tasks.report')->middleware('can:submit_task_report');
    Route::post('task-reports/{report}/approve', [App\Http\Controllers\TaskController::class, 'approveReport'])->name('tasks.report.approve')->middleware('can:approve_task_report');
    Route::resource('tasks', App\Http\Controllers\TaskController::class)->middleware('can:view_tasks');

    // Projects (Excel Fidelity Gap #1)
    Route::get('projects/report', [App\Http\Controllers\ProjectController::class, 'report'])->name('projects.report')->middleware('can:view_performance');
    Route::get('projects/export', [App\Http\Controllers\ProjectController::class, 'export'])->name('projects.export')->middleware('can:view_performance');
    Route::resource('projects', App\Http\Controllers\ProjectController::class)->middleware('can:view_performance');
    
    // Project Milestones
    Route::post('projects/{project}/milestones', [App\Http\Controllers\ProjectMilestoneController::class, 'store'])->name('projects.milestones.store')->middleware('can:view_performance');
    Route::put('milestones/{milestone}', [App\Http\Controllers\ProjectMilestoneController::class, 'update'])->name('milestones.update')->middleware('can:view_performance');
    Route::delete('milestones/{milestone}', [App\Http\Controllers\ProjectMilestoneController::class, 'destroy'])->name('milestones.destroy')->middleware('can:view_performance');

});
