# Approved Objective Editing Implementation Plan

**Goal:** Allow Plan and Report Experts, Directorates and Chiefs to edit approved objective details directly without recall, resubmission or a new approval workflow.

**Architecture:** Use one objective-level edit authorization rule for the controller and edit links. Keep approved status, ownership and entered performance. Require a change reason for the newly authorized approved edits, retain revision/audit tracking, and synchronize published plan values without resetting existing plan or assignment approvals. Keep the existing separate reporting-frequency approval rule and all pre-approval permissions unchanged. Published years and existing performers cannot be removed or reassigned through a details edit; additional performers receive the normal assignment workflow. Approved objectives must retain approved ancestry.

**Scope:** Update the current Plan checkout in place and preserve its existing uncommitted work. No changes to database credentials, working records, migrations, account roles or generated manuals.

## Verification and implementation

- [x] Add failing HTTP tests for all three planning roles: approved main/sub objective edit forms and links, saving with status/ownership/audit intact, and synchronized plan targets with existing assignment approvals and performance intact.
- [x] Cover denied departmental roles, missing change reasons, existing submitted-stage restrictions, frequency-change validation, and transaction rollback if publishing fails.
- [x] Run the new tests and confirm the failures are missing approved edit authorization.
- [x] Centralize edit authorization in `StrategicObjective`; update the controller and list/details views and show a reason field for approved edits.
- [x] Save approved edits and synchronize published values atomically through `ObjectivePublisher`, including calculated parent weights, without reapproving or deleting results.
- [x] Run the focused tests, the full PHP test suite, PHP syntax checks and diff whitespace checks; report any remaining failures accurately.

## Review refinements

The read-only review identified stale published records when a year or existing performer is changed, and loss of reporting-tree visibility when an approved activity is moved beneath unapproved ancestry. Both cases now have validation and regression tests. An additional regression test ensures editing objective details cannot silently approve an existing draft departmental plan. These safeguards do not introduce an objective reapproval workflow.

## Final verification

- PHP suite: 202 tests, 1,989 assertions, no failures; 2 reference-workbook tests skipped because their external workbook is unavailable.
- New approved-edit coverage: 19 test cases, including the three planning roles and structural preservation checks.
- PHP syntax checks passed for the controller, objective model, publisher service and new tests; targeted `git diff --check` passed.
- All test database writes used guarded in-memory SQLite. No working MySQL records, environment settings or database schema were changed.
