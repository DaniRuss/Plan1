# Stable K1 to K27 Crosswalk Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Assign stable K1 through K27 identifiers to corresponding historical and current activities without altering existing plan content.

**Architecture:** Add a nullable historical KPI-code column and a transactional crosswalk service that resolves exact existing rows, validates completeness and uniqueness, updates only KPI-code fields, and upserts approved exact-code mappings. Existing comparison services prioritize the permanent code.

**Tech Stack:** Laravel, Eloquent, MySQL, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-29-stable-kpi-crosswalk-design.md`

## Global Constraints

- No deletion, recreation, or modification of activity text, plan codes, targets, results, or workflow approvals.
- Exactly K1 through K27 once on each side.
- Exact permanent-code pairs are trusted and approved automatically; description-only suggestions remain draft until reviewers approve them.

## Review Focus

- Missing crosswalk endpoint must abort the whole transaction with no partial assignments.
- Duplicate endpoint or code must be rejected.
- Re-running must be idempotent and preserve existing approved review metadata.
- Main-objective KPI rows must be eligible for stable-code comparison.
- Existing description-based suggestions must never override an exact permanent-code match.

---

### Task 1: Historical KPI Code Storage and Crosswalk Tests

**Files:** migration, `app/Models/HistoricalKpi.php`, `tests/Feature/HistoricalKpiCrosswalkTest.php`

- [ ] Write failing tests for the 27-code contract, transaction rollback, and idempotency.
- [ ] Add nullable indexed `kpi_code` to historical KPIs and expose it on the model.
- [ ] Run the focused tests.

### Task 2: Transactional Crosswalk Assignment

**Files:** `app/Services/StableKpiCrosswalk.php`, `app/Services/HistoricalMappingSuggester.php`

- [ ] Implement the reviewed 27-row crosswalk and strict endpoint validation.
- [x] Update only both sides' KPI-code fields and upsert direct approved mappings.
- [x] Prefer exact permanent-code matches in future suggestions.
- [ ] Run focused tests and existing comparison regressions.

### Task 3: Live Database Update and Audit

**Files:** database only through migration/service execution.

- [ ] Apply the additive migration to local database `plan`.
- [ ] Execute the crosswalk service against historical import 1 and current objectives.
- [ ] Verify K1 through K27 once on each side and 27 matching mapping pairs.
- [ ] Compile views and run the comparison regression suite.
