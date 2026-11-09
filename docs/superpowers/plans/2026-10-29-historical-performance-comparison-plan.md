# Historical Performance Comparison Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a complete, auditable annual 2018-versus-2019 performance comparison with historical Excel import, reviewed KPI mappings, approval workflow, and a premium comparison page.

**Architecture:** Store historical imports and KPI rows separately from live planning data. Link historical rows to current strategic objectives through approved mapping records, and calculate the official comparison in a focused service that reuses current annual aggregation rules. Extend the existing comparison workspace without changing its current methods.

**Tech Stack:** Laravel, Eloquent, Blade, PhpSpreadsheet, Chart.js, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-29-historical-performance-comparison-design.md`

## Global Constraints

- Initial official comparison is final annual 2018 EFY versus final annual 2019 EFY.
- Missing results are null, never zero.
- Raw-value changes require compatible units and definitions.
- Existing comparison methods and unrelated uncommitted changes must remain intact.
- Historical imports are immutable except for audited unit and direction corrections.

## Review Focus

- Missing unit remains unspecified, editable, and excluded from raw comparison until approved.
- Split and merged mappings remain contextual unless explicitly marked comparable and approved.
- Incomplete or provisional current performance cannot enter official historical comparison.
- Lower-is-better KPIs calculate achievement and movement in the correct direction.
- Reimporting the same workbook checksum does not duplicate historical rows.

---

### Task 1: Historical schema and models

**Files:**
- Create: `database/migrations/2026_10_29_000001_create_historical_performance_comparison_tables.php`
- Create: `app/Models/HistoricalPerformanceImport.php`
- Create: `app/Models/HistoricalKpi.php`
- Create: `app/Models/HistoricalKpiMapping.php`
- Test: `tests/Feature/HistoricalPerformanceComparisonTest.php`

**Interfaces:**
- Produces Eloquent relationships, casts, workflow constants, and query scopes used by import, mapping, and comparison services.

- [ ] Write schema/model tests for uniqueness, nullable units, mapping multiplicity, and approval fields.
- [ ] Run the focused test and confirm failure before migration/models exist.
- [ ] Implement tables and models.
- [ ] Run the focused test and confirm pass.

### Task 2: 2018 workbook importer

**Files:**
- Create: `app/Services/HistoricalPerformanceImporter.php`
- Create: `app/Http/Controllers/HistoricalPerformanceController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/HistoricalPerformanceComparisonTest.php`

**Interfaces:**
- Produces `HistoricalPerformanceImporter::import(UploadedFile $file, int $year, User $actor): HistoricalPerformanceImport`.
- Consumes historical models from Task 1.

- [ ] Add failing tests for 16 main objectives, 244 activities, confirmed code corrections, the missing Rail laying Machine unit, normalized unit aliases, and checksum idempotency.
- [ ] Implement validated XLSX import and source-row preservation.
- [ ] Add controller upload route with role authorization and audit logging.
- [ ] Run focused tests.

### Task 3: KPI correction and mapping workflow

**Files:**
- Modify: `app/Http/Controllers/HistoricalPerformanceController.php`
- Create: `app/Services/HistoricalKpiMappingWorkflow.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/HistoricalPerformanceComparisonTest.php`

**Interfaces:**
- Produces unit/direction correction, mapping save, submit, approve, and reject endpoints.
- Mapping workflow exposes `save`, `submit`, `approve`, and `reject` operations with optimistic version checks.

- [ ] Add authorization and state-transition tests for expert preparation and director/head approval.
- [ ] Add tests proving missing units remain null and audited corrections trigger remapping review.
- [ ] Implement workflow service, validation, transactions, and audit records.
- [ ] Run focused tests.

### Task 4: Annual historical comparison calculator

**Files:**
- Create: `app/Services/HistoricalPerformanceComparison.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `app/Services/PerformanceComparison.php`
- Test: `tests/Feature/HistoricalPerformanceComparisonTest.php`

**Interfaces:**
- Produces `HistoricalPerformanceComparison::build(User $user, Request $request): array` with rows, averages, coverage, movement, units, raw changes, and warnings.
- Consumes approved mappings and current plan-item annual aggregation.

- [ ] Add failing tests for approved-only mappings, official-only performance, lower-is-better calculations, incompatible-unit suppression, missing data, and split/merged exclusions.
- [ ] Implement calculator and add `historical` comparison method routing.
- [ ] Run historical and existing comparison tests.

### Task 5: Complete comparison and mapping interface

**Files:**
- Modify: `resources/views/performance/_comparison-workspace.blade.php`
- Create: `resources/views/performance/_historical-comparison.blade.php`
- Create: `resources/views/performance/_historical-management.blade.php`
- Test: `tests/Feature/HistoricalPerformanceComparisonTest.php`

**Interfaces:**
- Consumes arrays produced by Task 4 and management data from the controller.

- [ ] Add view assertions for summary cards, coverage, annual-only warning, filters, chart, detailed values, import panel, unit correction, mapping status, and approval controls.
- [ ] Build responsive sections using existing application design conventions and accessible form labels.
- [ ] Keep Chart.js local behavior graceful when no matched rows exist.
- [ ] Run focused feature tests.

### Task 6: Regression and release verification

**Files:**
- Test: `tests/Feature/HistoricalPerformanceComparisonTest.php`
- Test: `tests/Feature/PerformanceComparisonMethodsTest.php`
- Test: `tests/Feature/DashboardPerformanceComparisonTest.php`

**Interfaces:**
- Verifies all earlier deliverables together.

- [ ] Run PHP syntax checks on every new and modified PHP file.
- [ ] Run the historical comparison tests.
- [ ] Run all existing comparison tests.
- [ ] Run the full relevant feature suite and report unrelated failures separately.
- [ ] Render the comparison page or inspect generated HTML to verify layout, labels, and empty states.
