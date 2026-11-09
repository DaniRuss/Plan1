# Department self-comparison Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement inline task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Replace department benchmarking with annual self-comparison and allow independent fiscal-year sources in period comparisons.

**Architecture:** Reuse AnnualDepartmentEvidence for two selected-year scorecards, keeping the existing score calculator. Extend Workbench period selection and its form/JS transitions so both sources are independent and period granularity is validated. Keep original source/authorization/completeness guards.

**Tech Stack:** Existing Laravel/PHP/Blade, Chart.js and plain JS/CSS; no dependencies/migrations.

**Spec:** `docs/superpowers/specs/2026-11-05-department-self-comparison-design.md`

## Global Constraints

- Existing Plan checkout; preserve unrelated dirty edits. No commits/branch changes or database/environment/credential writes.
- Departments is ONE department across TWO different fiscal years, annual only, no cross-department pairs/ranks.
- Reference and Comparison labels; internal current_* parameter keys remain backward compatible.
- Historical workbook annual only; native months/quarters remain selectable for future years and same-year comparisons.

## Review Focus

- Both fiscal sources and single department must be validated; old different-department URLs cannot silently benchmark departments.
- No reference source, same-year calendar aliases, reversed year order and missing complete evidence must not yield false deltas.
- Source/type/nav/swap transitions preserve the independent years and compatible period lengths without self-comparison.
- Shared historical responsibility and changed annual activities/weights must remain explained, with no approvals or fake matched-KPI claims.
- Annual rows and detailed evidence, CSV, charts and mobile reflect the same selected department and both original sources.

## Task 1: Annual self-comparison

Modify AnnualDepartmentComparison, annual controls/result view, navigation, JS and AnnualDepartmentComparisonTest; preserve AnnualDepartmentEvidence rules.

- [x] Write RED tests: one department history80/native85 => +5pp; native 2026/2027 scores85/95 => +10pp; different departments and same-year aliases rejected; missing reference/results handled; changed populations flagged. Retain missing/zero/weights/attribution regressions under the new contract.
- [x] Implement two-source annual payload/cards, single department selector, independent source selectors, no ranks/all/pair options. Render two annual scorecards, comparison table/chart, per-year evidence and CSV.
- [x] Run annual tests GREEN; adjust only old tests intentionally asserting superseded department benchmarking.

## Task 2: Flexible period sources and terminology

Modify ComparisonWorkbench, PerformanceComparison routing, comparison builder/navigation/JS and paired/historical visible copy; extend Master/Methods/JS tests.

- [x] Write RED tests for same month/quarter across 2026/2027, adjacent same-year months, historical/native annual, self/mixed lengths/imported month rejection and independent JS source/swap transitions.
- [x] Unlock both source selectors, reconcile period options with source granularity, resolve safe defaults per source, retain shared department scope and remove same-year restriction. Apply Reference/Comparison visible copy without renaming stored data.
- [x] Run comparison PHP/JS tests GREEN and all mode navigation regressions.

## Task 3: Verification and handoff

- [x] Full guarded PHP suite and all JS tests; syntax/Pint/whitespace. Record unavailable external workbook skips.
- [x] Private SQLite desktop/mobile browser: single department, two sources, monthly/quarterly/annual, swap/source/type/nav, evidence/CSV/charts; no working demo data.
- [x] Read-only plan builds and zero changing queries (earlier check succeeded; final recheck blocked by stopped MySQL). One scoped fresh reviewer; important findings RED/GREEN plus final suite. Update user documentation and stop only private QA server.

## Execution decisions

The user explicitly approved the corrected design and requested implementation including future month/quarter comparisons. The spec records that approval rather than requesting another approval round. Work remains inline/in-place per their project preference; do not commit their pending work.
