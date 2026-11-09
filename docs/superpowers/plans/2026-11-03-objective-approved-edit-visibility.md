# Approved Objective Edit Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans for inline implementation. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show only the latest post-approval editor's name, role and edit time on objective/activity lists, retaining changes, reasons and all revisions on Details/View.

**Architecture:** Extend existing audit JSON with an immutable role snapshot, derive the latest real approved edit from audit records, and eager-load summaries throughout the existing hierarchy. Reuse the existing revision Details page and preserve permissions, approvals and published performance.

**Tech Stack:** Laravel, Eloquent, Blade, guarded SQLite PHPUnit tests.

**Spec:** User-approved conversation on 2026-11-03: "Who edited them, their role, and when. only this should be seen out side the details page the rest should be shown on the details page ... and apply all your solutions".

## Global Constraints

- Outside Details/View, show only editor, role and time; no field diffs, comments, reasons or revision counts.
- Approval status and edit permissions remain unchanged.
- Preserve all existing audit history and user worktree changes; do not fabricate historical roles or change the working database.
- Future revisions retain role-at-edit snapshots; legacy roles must be explicitly identified as current-role fallbacks or unavailable.

## Review Focus

- Nested activities and main objectives both need correctly associated summaries.
- No-op edits and pre-approval-only revisions must not create post-approval markers.
- Later role/name changes and deleted editor accounts must not rewrite stored attribution.
- Latest approved attribution must survive later draft edits or no-op saves.
- Lists must not add one database query per row or embed hidden audit details.

## Task 1: Attribution and visibility

**Files:** `app/Services/ObjectiveRevisionHistory.php`, `app/Models/StrategicObjective.php`, `app/Http/Controllers/StrategicObjectiveController.php`, `resources/views/objectives/{index,show}.blade.php`, `resources/views/objectives/partials/{hierarchy-row,edit-attribution}.blade.php`, `tests/Feature/ObjectiveRevisionVisibilityTest.php`.

- [x] Write failing feature tests for main/nested attribution, privacy, historical role snapshots, no-ops, later draft edits, legacy/deleted editors, and bounded queries.
- [x] Run the new tests and confirm failures are missing attribution/snapshots rather than setup errors.
- [x] Save role snapshots in existing revision JSON, select the latest meaningful approved audit, and render one reusable metadata partial at each hierarchy level.
- [x] Enhance Details/View with saved role attribution and keep reasons/before-after values in that page only.
- [x] Record automatically changed approved ancestor weights as dependent revisions only when their values actually change.
- [x] Run the new tests and existing approved-edit regressions.

## Task 2: Verification

- [x] Run the complete PHP suite and syntax checks.
- [x] Inspect the actual application list and Details rendering in the private loopback QA fixture, not the working database.
- [x] Independent read-only review; fix important findings with regression tests.

## Execution rulings

- Implement directly in the active `C:\xampp\htdocs\Plan` checkout as explicitly requested, preserving unrelated dirty changes; do not create another workspace or database.
- The user's instruction to apply the approved design is the execution handoff; no further design or execution-choice questions are needed.

## Verified results

- New attribution regressions plus approved-edit and reporting-frequency regressions: 38 tests, 358 assertions, all passing.
- Complete PHP suite: 234 tests, 2,194 assertions, zero failures/errors, two skipped external-workbook tests (232 passing).
- Browser QA of the actual app source in the private training fixture: HTTP 200; correct attribution on main, sub-objective and nested activity; no reason/diff on list; full evidence on Details; no JavaScript page errors.
- PHP syntax and scoped diff whitespace checks passed. No working database changes or migrations are needed.

Independent-review fixes were regression-tested: newest edit selected by timestamp with ID tie-break; approved notifications link to Details instead of exposing reasons; pending/rejected frequency requests are not treated as applied edits; frequency approvals save the editor's role snapshot. Duplicate live-account attribution was removed from normal structured-history rendering so later account renames do not contradict the preserved editor snapshot. Edit-form help now correctly directs owners to the Details history.
