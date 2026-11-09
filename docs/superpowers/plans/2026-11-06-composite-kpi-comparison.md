# Composite KPI Comparison Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans. Work inline with test-first gates and one final independent review. Do not commit or switch branches in this existing dirty checkout.

**Goal:** Show traceable weighted composite KPI achievements instead of misleading blank main-row results.

**Architecture:** Enrich authorized comparison frames with a dedicated roll-up service. Preserve raw/direct measurements and reuse the derived record in selection, summaries, paired UI and exports.

**Tech Stack:** Existing Laravel/PHP, Blade, CSS, PHPUnit and browser QA. No new dependencies.

**Spec:** `docs/superpowers/specs/2026-11-06-composite-kpi-comparison-design.md`

## Global Constraints

- No schema, credentials, account, objective, mapping or performance records are changed.
- Keep raw targets and actuals null for derived composite records.
- Missing is not zero; direct measurements and manual overrides remain direct.
- Respect authorization, exclusions, allocation ambiguity and source review status.
- Preserve the existing dirty checkout and all unrelated changes.

## Review Focus

- Nested/direct containers must not be counted with their own descendants.
- Department/frequency filtering must not make an incomplete group look complete.
- Changed component definitions/relative weights must not become like-for-like growth.
- Explicit zero and mixed units are valid at activity level; missing weights and results are not zeros.
- CSV and expandable detail must expose the same contributing evidence and numeric values.

### Task 1: Derived composite evidence

**Files:** Create `app/Services/ComparisonCompositeRollup.php`; modify `app/Services/ComparisonEvidence.php`; create `tests/Feature/CompositeKpiComparisonTest.php`.

**Interfaces:** `ComparisonCompositeRollup::apply(Collection $entities, Collection $records): array` returns enriched `entities` and `records`. Records expose `measureType`, `rollup` (weight, score, completed, expected, components, warnings) while preserving raw source values.

- [ ] Write HTTP/service regression fixtures for the 13 historic K22 activities (score 4.4705, weight 5, achievement 89.41), mixed units/shared performers, native equivalents and missing results. Run and observe failure.
- [ ] Add authoritative weights, source completeness, roll-up inclusion and direct-measure metadata to comparison frames; derive weighted roll-ups without persisted changes.
- [ ] Test nested containers, direct/manual measurements, excluded children, zero, unknown weights, and authorization/filter coverage. Run focused tests to green.

### Task 2: Selection, pairing and aggregate safeguards

**Files:** Modify `app/Services/ComparisonWorkbench.php`; extend `tests/Feature/CompositeKpiComparisonTest.php`.

**Interfaces:** Use Task 1 derived records. Pair output includes explicit composite comparison warnings and component correspondence; summaries remove descendants when their composite is selected.

- [ ] Add failing tests for compatible composite pairs, revised composition/weights, mixed direct/composite pairs, and no double-counting.
- [ ] Preserve direct KPI behavior; calculate composite delta only for complete compatible component sets, leave raw target/actual differences unavailable for composites.
- [ ] Run composite and existing master/period/historical comparison suites to green.

### Task 3: Explainable screen and exports

**Files:** Modify `resources/views/performance/_paired-comparison.blade.php`, `public/css/performance-comparison.css`; add `resources/views/performance/_composite-evidence.blade.php`; update `docs/performance-comparison-workspace.md` and tests.

**Interfaces:** Render rollup metadata and paired component evidence, export explicit weighted score fields and all contributing rows using existing CSV controls.

- [ ] Add failing HTML/export payload assertions for labelled points, no fake target/actual, expandable activity detail, source evidence and composition warnings.
- [ ] Implement accessible responsive detail, clear provisional labels and safe CSV payloads.
- [ ] Run full PHPUnit and JavaScript suites, syntax and diff checks; compare read-only live K22 results against original workbook and inspect desktop/mobile output.
- [ ] Request one independent read-only review, address important findings with regression tests, record final evidence.
