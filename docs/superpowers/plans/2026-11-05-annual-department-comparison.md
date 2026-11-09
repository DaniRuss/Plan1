# Annual Department Comparison Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Replace departmental common-KPI comparison with an annual-only weighted department scorecard for native and imported annual evidence.

**Architecture:** An annual evidence adapter resolves source activities/attribution and a department orchestrator calculates completeness, scores, ratings and ranks. Route both departmental entry points to it and render an annual-specific workspace while keeping the remaining comparison modes intact.

**Tech Stack:** Existing Laravel/PHP, Blade, Chart.js and plain JS/CSS; no dependencies or schema changes.

**Spec:** `docs/superpowers/specs/2026-11-05-annual-department-comparison-design.md`

## Global Constraints

- Active Plan checkout only; preserve unrelated dirty edits and no commits/worktree switching.
- No working data/environment/schema/credential writes or automatic attribution/weight approvals.
- Annual-only totals: ignore stale period/frequency/activity filters; source IDs and department IDs remain authorized.
- Imported reported percentages and source rows are retained; uncertain historical shared attribution stays pending.

## Review Focus

- Missing annual results/weights, explicit zero, partial reviewed weeks, duplicate approved items and parent/descendant double counting must not yield a final rank.
- Shared or partially unknown historical performers, inherited responsibility and missing alias targets must neither invent departmental numbers nor recurse indefinitely.
- A departmental annual total must not depend on shared KPI codes, number of activities, activity search or frequency filters carried from Master.
- Legacy department URLs must reach the annual workspace; aliases resolve one existing source and other comparison modes stay unchanged.
- All-department/pair/source transitions, rankings/ties, CSV and mobile charts must use the same calculated population and preserve access restrictions.

## Task 1: Annual evidence and score calculation

Files: create `app/Services/AnnualDepartmentEvidence.php`, `app/Services/AnnualDepartmentComparison.php`, `tests/Feature/AnnualDepartmentComparisonTest.php`; protect alias recursion in `ObjectivePerformerMatcher.php` with regression coverage.

Interfaces: `AnnualDepartmentEvidence::load(User $user, array $source, Collection $departments): array` returns filename, per-department activity collections and unattributed-source warnings. `AnnualDepartmentComparison::build(User $user, Request $request, Collection $sources, Collection $departments): array` returns `workspace=departments`, annual builder state, departmentRows, ranks, pair difference and chart/export-ready details.

- [x] Write and run failing tests for different department activities with hand-calculated weighted scores (80 at weight 75 plus 100 at 25 = 85), historical annual ownership, missing/zero/partial results, zero or missing weights, hierarchy, duplicate records and shared attribution.
- [x] Implement adapters using existing annual calculator/weights and matcher. Keep native department-specific evidence distinct from historical company-wide shared output.
- [x] Run new tests green and existing annual/calculator/performer regressions; record results.

## Task 2: Annual-only routing and workspace

Files: modify `PerformanceComparison.php`, `ComparisonWorkbench.php`, dispatcher/builder/navigation, `comparison-workbench.js` and comparison CSS; create annual controls/result view; extend PHP rendered-form and JS behavior tests.

- [x] Add failing route/form tests for Master, focused/legacy links, authorized sources, pair/all views, stale-filter isolation, ratings, ties and source transitions.
- [x] Route department mode before generic KPI pairing and use annual-only controls/results with per-department drilldowns, coverage, chart and CSV. Preserve the five other modes.
- [x] Update old department tests to the intentionally changed annual contract without weakening unrelated period/target assertions; run comparison PHP/JS tests green.

## Task 3: End-to-end verification and review

- [x] Full guarded SQLite PHP suite, JS tests, syntax/Pint/whitespace; report unavailable external workbook skips.
- [x] Browser checks on private SQLite for native/historical, pair/all, Master/focused, annual source transitions, details, CSV, charts, mobile and no page errors.
- [x] Read-only `plan` check of annual native/historical modes and unchanged source counts; no working-data writes.
- [x] One independent read-only scoped review; important fixes RED/GREEN and final suite. Update page documentation and hand off verified behavior.

## Execution ledger

- Baseline: 276 PHP tests / 2391 assertions, zero failures/errors, two external-workbook skips. Existing `Plan` worktree and source changes preserved.
- Pre-flight: Task 1 source-prefixed annual activity records feed Task 2 department rows and controls; all modes share the existing source catalog and navigation. Annual mode must branch before generic source/period restrictions and ignore carried activity filters.
- Ruling: execute the user-approved written proposal inline in the current checkout and record it here rather than requesting a duplicate approval or moving the project. No git commit or branch operation is authorized or needed for this in-place app change.
