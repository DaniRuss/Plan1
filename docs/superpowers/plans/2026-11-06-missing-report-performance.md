# Missing Report Performance Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans inline. Steps use checkbox syntax.

**Goal:** Reports and Master Report distinguish missing, zero, partial and complete evidence, including downloads.

**Architecture:** A read-only ReportPeriodEvidence service resolves official period actuals and completeness. Report controllers and the hierarchy calculator carry nullable scores and accurate coverage to views and exports.

**Tech Stack:** Existing Laravel/PHP, PHPUnit with isolated in-memory SQLite, Blade and PhpSpreadsheet.

**Spec:** docs/superpowers/specs/2026-11-06-missing-report-performance-design.md

## Global Constraints

- No migrations, configuration changes or edits to existing database records.
- Implement in the existing Plan checkout and preserve unrelated working changes.
- Only final-reviewed, non-null evidence counts as official performance.

## Review Focus

- Real zeroes, including lower-is-better and zero-target indicators, must remain reportable.
- Weekly months require every valid calendar week; ignore wrong-year and unapproved records.
- Checkpoint quarters with a missing final month must not invent a zero or final score.
- A missing child must not depress a parent's reported-only score or disappear from coverage.
- HTML, PDF, Excel/CSV and report-list summaries must agree on missingness.

### Task 1: Shared evidence and report calculations

**Files:** Create app/Services/ReportPeriodEvidence.php; modify EdrReportController, EdrPerformanceCalculator; add tests/Feature/MissingReportPerformanceTest.php.

**Interfaces:** ReportPeriodEvidence::forItem(PlanItem $item, string $period): array returns nullable target/actual, reported/complete/pending flags, expected/completed counts and coverage. Controllers pass these fields to EdrPerformanceCalculator::apply; summary retains null scores without evidence and labels provisional totals.

- [ ] Write failing tests for absent increased/decreased KPIs, pending approval, entered zero, incomplete weekly/monthly/annual reporting and hierarchy scoring.
- [ ] Run new tests and observe the current zero/default failures.
- [ ] Implement the shared evidence resolver and adapt report scoring without modifying PlanItem's existing helper.
- [ ] Run new tests and existing report/calculator tests; expected no failures.

### Task 2: Master report and delivery surfaces

**Files:** Modify MasterReportController, reports Blade views, ReportPerformanceExport and MasterPerformanceExport; extend the missing-performance regression tests.

**Interfaces:** Both controllers consume Task 1's period evidence. Master cells carry nullable actual/achievements, complete/pending flags and accurate expected/completed/coverage. Views and exports never cast a missing metric to zero.

- [ ] Add failing quarter, checkpoint, weekly coverage, HTML and export tests.
- [ ] Run and confirm failures for fabricated zeroes/complete coverage.
- [ ] Implement Master evidence/roll-ups and consistent display/export handling.
- [ ] Verify integration tests, real XLSX cell values, PDF view rendering and report-list summaries.

### Task 3: End-to-end verification

**Files:** Existing tests and read-only live diagnostics; docs/report-performance-evidence.md.

- [ ] Run the complete PHP test suite and existing JavaScript tests.
- [ ] Inspect the final diff and obtain one independent focused review.
- [ ] Reproduce Operation February 2026 using a read-only transaction; expected no performance/achievement instead of 16.54%, and unchanged record counts.
- [ ] Verify partial Master quarters no longer claim 100% coverage; document behavior and checks.
- [ ] Leave changes uncommitted for user review; no deployment or database writes.
