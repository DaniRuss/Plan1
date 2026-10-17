# Project review - 17 October 2026

## Scope and evidence

Reviewed routing, role/permission gates, shared and legacy reporting, task/employee performance, projects/milestones, notification integration, migrations, recent logs, frontend build, and automated tests. This is a code and automated-check review; it is not a manual walkthrough of every page or a guarantee that every calculation matches the organization's rules. No application fixes were applied during this review.

Three problems were reproduced using temporary tests against the isolated in-memory SQLite database: an auditor deleting a project, a chief forwarding a monthly shared report outside the submission window, and a chief correction resetting metric workflow state to draft. These tests assert the observed defects, not correct behavior. No production records were changed by those tests.

## Priority 1: authorization and approval integrity

### 1. Viewing permission permits project writes

Files: `routes/web.php:253`, `app/Http/Controllers/ProjectController.php:58`, `app/Http/Controllers/ProjectMilestoneController.php:12`, `database/seeders/RolesAndPermissionsSeeder.php`.

All project resource methods and milestone mutations use `can:view_performance`. Controllers do not enforce write permissions or department ownership. A view-only auditor successfully deleted a project in the isolated reproduction. The same route design exposes creation and updates to viewing roles.

Update: separate viewing from project/milestone management permissions; apply policies to each record and scope lists/exports by department. Add negative tests for auditors, experts, other departments, and inactive users.

### 2. Legacy monthly entry bypasses approval and certification

Files: `app/Http/Controllers/PerformanceController.php:77`, `app/Http/Middleware/SharedReportingWorkspace.php:27`, `routes/web.php:108`.

`PerformanceController::update()` writes `status = approved` directly, including derived parents. It does not check current workflow state or period closure. The shared-workspace middleware only intercepts departments with a reporting team and preserves legacy periods already in review. Consequently this old endpoint still bypasses approval controls for eligible legacy users/periods.

Update: route monthly writes through one workflow service, enforce period closure and role/stage checks on every write, and stop automatically approving entry saves. Keep historical reports readable without leaving historical approvals editable.

### 3. Task operations lack record authorization

Files: `routes/web.php:248`, `app/Http/Controllers/TaskController.php:60`, `app/Http/Controllers/TaskController.php:105`, `app/Http/Controllers/TaskController.php:111`, `app/Http/Controllers/TaskController.php:158`.

Task creation uses the resource route's viewing permission rather than `assign_task`. Viewing a task does not verify scope. Reporting checks neither task ownership nor the assigned employee; approval checks neither the relevant department nor the current report state. Index filtering does not protect direct requests to these endpoints.

Update: add task/report policies for assignment, viewing, submission and approval; bind submissions to the assigned employee and prevent self-approval or repeated approval where disallowed.

## Priority 1: broken features

### 4. Task pages and evidence submission are incomplete

Files: `app/Models/Task.php`, `app/Models/TaskReport.php`, `app/Models/TaskEvidence.php`, `app/Http/Controllers/TaskController.php:107`, `app/Http/Controllers/TaskController.php:140`, `app/Http/Controllers/EmployeePerformanceController.php:22`.

`Task::reports()` and `TaskReport::evidence()` are absent although controllers eagerly load them. `TaskEvidence` is an empty Eloquent model with no fillable attributes, but reporting calls `TaskEvidence::create(...)`; this conflicts with default mass-assignment protection. Task detail, nonempty My Tasks/employee scorecards, and mandatory evidence submission therefore have concrete failure paths.

Update: implement the relationships and explicit evidence fillable/casts, then test a complete assign -> submit with evidence -> review -> employee report/export flow. Delete stored files if submission fails; the current catch rolls back database rows but leaves uploaded files behind.

### 5. Fifteen registered routes point to missing methods

File: `routes/web.php` and the corresponding controllers. Verified by reflecting the registered Laravel route collection.

| Controller | Missing methods exposed by resource routes |
|---|---|
| PlanController | edit, update, destroy |
| EdrReportController | destroy |
| UserController | show |
| CargoController | edit, update, destroy |
| TrainJourneyController | show, edit, update, destroy |
| TaskController | edit, update, destroy |

Update: restrict each resource with `only()`/`except()` to implemented operations, or implement the intended action with authorization and validation. Check related links and forms so users cannot navigate to unavailable operations.

## Priority 2: reporting consistency

### 6. Shared reporting ignores the configured chief submission window

Files: `app/Services/SharedReporting.php:300`, `app/Http/Controllers/MonthlyAggregationController.php:731`, `app/Http/Controllers/MonthlyAggregationController.php:1094`.

The legacy monthly chief-forward action calls `ensureSubmissionWindowOpen()`. Shared `approve_chief` does not. An isolated test configured an already-expired window and successfully forwarded a shared monthly report.

Update: extract a common submission-window guard and apply it to the shared transition. Test before, during and after the window and any explicitly intended administrator exception.

### 7. Corrections reset metric workflow to draft

Files: `app/Services/SharedReporting.php:444`, `app/Services/SharedReporting.php:457`.

`persistMetric()` always writes draft workflow values. It is also used for directorate and chief corrections, where the report must remain at its current review stage. The save method does not synchronize the leaf metric afterward. Reproduction: the submission stayed `submitted_to_chief` while its edited weekly metric became `draft`. Monthly report rendering reads workflow state from the first metric row, making disagreement especially problematic.

Update: derive the row's effective workflow state from the submission and section when saving, preserve review metadata appropriately, and synchronize derived/leaf rows consistently. Add correction tests at each editable stage and for all reporting frequencies.

### 8. Legacy monthly save lacks validation and atomicity

File: `app/Http/Controllers/MonthlyAggregationController.php:446`.

`entryStore()` validates uploads but not the entries/actual values/notes/remarks it writes. Leaf updates, rollups, remarks and attachment metadata are not wrapped in a transaction, and this method does not enforce the month's closure. These paths remain relevant for departments/periods using the legacy workflow.

Update: validate the complete request, distinguish blank values from explicit zero, enforce closure, save database changes transactionally, and clean up uploads on failure.

### 9. Shared reporting repeats queries and full rollups per row

Files: `app/Http/Controllers/SharedReportingController.php:57`, `app/Services/SharedReporting.php:217`, `app/Services/SharedReporting.php:175`, `app/Services/SharedReporting.php:495`.

Report rendering invokes metric queries separately for every activity (monthly values issue both first-row and source-row queries). Batch saves call `save()` per row, which recalculates the whole derived hierarchy after every changed row. This is a scaling concern, not a measured explanation for every timeout. Recent local logs contain repeated 30-second execution timeouts, including 17 October.

Update: bulk-load metric/station/day values, validate and persist the batch first, then recalculate once. Measure query counts and latency on a realistic large department before changing runtime limits.

## Priority 2: account/session controls

### 10. Login throttling and active-session checks are missing

Files: `routes/web.php:24`, `app/Http/Controllers/Auth/LoginController.php:22`, `app/Providers/AppServiceProvider.php:31`, `bootstrap/app.php`.

Login rejects inactive accounts but has no route throttle or rate limiter. Existing authenticated sessions are not centrally rejected when an account becomes inactive; some shared-report methods check activity, but global permission gates and many other controllers do not.

Update: throttle login attempts, add a central active-user middleware or session invalidation on deactivation, and test an account deactivated during an existing session.

## Suggested implementation order

1. Close project/task authorization and legacy approval bypasses.
2. Restore the task relationships/evidence model and remove broken routes.
3. Unify shared/legacy stage, closure and submission-window rules.
4. Add MySQL integration coverage alongside SQLite tests, particularly for enums and constraints. The earlier status error passed SQLite tests but failed MySQL.
5. Optimize batch saves and page query loading with measured query/latency checks.

## Validation

- Database migration status: all listed migrations applied; no pending migrations observed.
- Frontend: `npm.cmd run build` passed. `npm` alone hit this machine's PowerShell script policy; using the standard Windows command wrapper succeeded.
- JavaScript: number-format test file passed (18 internal cases).
- Isolated defect reproductions: 3 passed, 29 assertions, confirming the observed defects above.
- Full PHP suite: 141 passed, 2 skipped, 1,230 assertions (285.67 seconds). Both skips require a reference workbook unavailable on this machine. Passing tests do not cover all defects described above.
- Resource route reflection: 15 endpoints reference missing controller methods.

## Files changed by this review

Only this review document is retained. Temporary inspection/reproduction files are removed after verification. Frontend build regenerated ignored files under `public/build`. Application source was not changed.
