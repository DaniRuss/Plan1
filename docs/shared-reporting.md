# Shared department reporting

## Activate a department

1. Open **Shared Reporting** from the sidebar and select the department.
2. The department chief or system administrator chooses **Assign coordinator and backup**.
3. Choose two different, active department experts and record an assignment reason.
4. Experts open a period using its approved plan, month, frequency and reporting week.
5. Check reviewer assignments. Each activity inherits the active directorate from its approved objective assignment. The chief can assign or replace a reviewer with a recorded reason and mark evidence mandatory.

No users are assigned automatically. Team configuration activates the new workspace for new and draft periods. Periods already in the old approval workflow stay there until completion. Historical performance values are not migrated or recalculated by the schema migration.

## Enter and review

All department experts share draft activities. Use **Save all activities** to save all editable values and Completion states in one transaction. Each activity has a version number; a conflict or validation failure rolls back the entire save. If another person changed it, the stale save receives HTTP 409: reload, compare the saved values, and then re-enter the correction. There is no force-overwrite action.

Every activity must be ready or explicitly not applicable with a reason before its draft section can be submitted. Zero is a real entered value; a blank is never interpreted as an entered zero. Daily readiness requires an entry for every day of the reporting week. Evidence is private and is required only where the chief has enabled that requirement.

The coordinator or backup submits the report. Each named directorate reviews and may correct only its own section while awaiting review. The chief may correct the consolidated report at the chief review stage. Reviewer corrections require a reason and preserve before/after values in the audit history. Returned sections are editable by department experts and must be marked ready again. Previously reviewed sections remain locked and approved. When all sections pass review, the chief consolidates and forwards the report through Plan & Report Expert, Directorate, and Head Office.

The chief can return selected sections to their directorates. Head Office can return the report to the chief, who chooses which sections need correction. Reviewer replacements reopen the receiving section for a fresh review. Final approval locks the report.

Daily values update their existing weekly summary. Weekly and monthly records use the existing performance tables and calculations, so existing monthly aggregation and downstream reports consume the approved source values without a second monthly data-entry step. Explicit not-applicable activities have no manufactured zero actual; their reason remains visible in the shared workspace. A monthly KPI formally approved as not applicable is excluded from scorecard ranking inputs; weekly KPIs are excluded for a month only if all reporting weeks are formally not applicable.

## Weekly Workbench and department approval

Weekly Workbench remains a separate, read-only weekly table for shared periods, with the original month/week selectors, planned values, actuals and achievement percentages. Draft shared values do not appear there. The chief's **Approve department results and send to Plan & Report** action publishes a snapshot, including station data. This is department approval, distinct from final Head Office approval.

Plan & Report can review, forward or return a published report from Workbench. During corrections the table retains the last department-approved snapshot with a returned-for-correction label; the chief's next approval replaces it. Editing Shared Reporting does not silently change published figures. Legacy periods already under review keep their previous workflow.

Manual period weight distribution remains unchanged: unplanned activities require no period weight. Missing approved weights do not block performance submission or review. Official weighted scoring continues to use the existing approved-weight rules. Existing final-approved monthly aggregation rules remain unchanged.

## Deployment and recovery

Deploy the migration, models, shared reporting service/controller/middleware/views, route and layout changes, dashboard/scorecard integrations, and the three reporting-controller rollup adapters together. Back up the database, then run `php artisan migrate --force` and `php artisan view:clear` before serving requests with the new code. No queue worker is required for the database notifications used by this module.

Existing departments are not activated until the chief assigns the team. New submissions are unique by department, plan year, month, frequency and week. Reviewer snapshots and audit events preserve who handled each report even if the source objective assignment changes later. Daily and weekly reports retain the configured weekly deadline; late submissions remain possible and are identified on the dashboard.

Rollback application code before removing the schema. Once reports exist, preserve the reporting tables and evidence files; dropping them loses assignment snapshots, readiness and review history. Existing performance tables retain the recorded metric values.


### Workflow notifications

Shared reporting uses the existing in-app notification bell and notification inbox. Notifications link directly to the report and include its department, frequency, year, month, and week where applicable. Return notifications include the reviewer comment.

- Submission and resubmission notify the reviewers of the sections being submitted.
- A returned section notifies department experts and the coordinator/backup.
- Section review completion updates coordinators; completion of all sections notifies the department chief.
- Chief approval, Plan and Report forwarding, and Head Office forwarding notify the next review role.
- Returns notify the chief, Plan and Report expert, directorate, or selected section reviewers responsible for corrections.
- Final approval notifies department experts, coordinators, the chief, assigned reviewers, and Plan and Report reviewers.
- Reviewer reassignment notifies the assigned reviewer; reporting team changes retain their existing notifications.

Notifications are sent after the database transaction commits. Recipients must be active, department recipients stay within the report department, duplicate recipients are removed, and the acting user does not receive their own notification.


## Weekly and monthly correction escalation

For weekly and monthly shared reports, the Plan & Report Expert chooses **Reviewed - No Correction Required** or **Request Correction Through Directorate**. Both send the report to the Plan & Report Directorate; correction requests require a comment of at least five characters. The Directorate forwards to the Plan & Report Chief (the existing `plan_report_head_office` role). Only that Chief/Head Office role can return the report to the Department Chief. Direct expert/directorate returns to the Department Chief are rejected on the server. Daily shared transitions are unchanged.

The Department Chief corrects and resubmits to the Plan & Report Expert. The last department-approved publication remains visible until resubmission. Correction comments accompany notifications and are retained when the Directorate forwards without a replacement comment. Legacy weekly reporting already uses this escalation route.
