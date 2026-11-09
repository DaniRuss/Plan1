# Master Comparison Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Make the Master a complete comparison builder for historical and future fiscal-year data, with focused presets and trustworthy paired evidence.

**Architecture:** Add an authorized source/evidence provider and a Master orchestrator around the existing historical and recorded-period providers. Add one conditional two-sided builder and a reusable paired result presentation; retain existing result presentations when their provider is selected.

**Tech Stack:** Laravel/PHP, existing Eloquent models and calculator, Blade, existing Chart.js selector, plain JavaScript/CSS. No new dependencies or schema.

**Spec:** `docs/superpowers/specs/2026-11-04-master-comparison-design.md`

## Global Constraints

- No working database writes, environment/schema changes, credential changes, fabricated historical periods or automatic mapping approval.
- Existing dirty edits remain in the active Plan checkout. No commits or worktree switching for this repair.
- User approved proceeding with the agreed design; execute inline without another preference gate.

## Review Focus

- Unauthorized source/entity/department IDs must never expose selectors or result data.
- A repeated KPI code is not enough when identity is ambiguous, definitions are explicitly changed or units/direction conflict.
- Native 2027 versus 2026 must work with complete annual values, not just explicit annual rows; incomplete evidence stays pending.
- Source/year changes invalidate obsolete entity selections cleanly; swap must preserve a meaningful pair and never create an accidental self-comparison.
- Different objective populations, duplicate accountable items and partial periods must not be presented as a like-for-like improvement or summed across units.

## Task 1: Authorized sources and paired entity evidence

Files: create `app/Services/ComparisonEvidence.php`, `app/Services/ComparisonWorkbench.php`, `tests/Feature/MasterComparisonTest.php`; integrate at `PerformanceComparison.php`.

Interfaces: evidence catalog returns source-keyed authorized entity metadata and normalized measurements; workbench returns builder state plus existing or paired result payload.

- [x] Write failing tests: all six Master modes; K7 historical/native pair despite renamed wording; future 2027/2026 pair with stable K7; missing/incomplete/zero actual; different KPI context; unauthorized selectors; historical monthly rejection; distinct same-year periods/departments.
- [x] Run the new file RED against guarded in-memory SQLite.
- [x] Implement source/year resolution, independent selections and pairing with existing annual calculator and scope rules. Keep original providers callable without recursive Master routing.
- [x] Run new and all existing comparison feature files GREEN; record any compatibility changes explicitly.

## Task 2: Complete Master and focused presets

Files: create builder and paired-result Blade partials; modify comparison dispatcher/navigation, existing historical/native views, comparison CSS/JS.

- [x] Add failing rendered-form tests for mode selection and independent source/entity fields on Master, with focused forms limited to their purpose.
- [x] Implement conditional Reference/Current selectors, scoped catalogs, Compare/Reset/Swap, persistent shared filters and evidence labels.
- [x] Keep original source values and administration on historical results; add matched/available coverage and full source evidence to native annual/entity results. Preserve chart scrolling and CSV/pagination.
- [x] Verify form submissions and source transitions for all modes, plus current-only/future-year empty states.

## Task 3: End-to-end verification and review

- [x] Run full PHP suite, all JS tests and syntax/whitespace checks; read results and name any failures/skips.
- [x] Browser-test all six Master comparisons, focused presets, KPI pairing, swap/source changes, scrolling, charts, CSV and mobile containment using private SQLite fixtures only.
- [x] Read-only build against `plan`; no imported or native values changed.
- [x] One fresh read-only review of this scoped implementation; fix important findings with RED/GREEN tests and rerun suite.
- [x] Update documentation and hand over with verified outcomes and any genuine remaining limits.

## Execution ledger

- Setup: active checkout `C:\xampp\htdocs\Plan`; supplied Plan&report path does not exist. Prior comparison edits and unrelated user changes preserved.
- Pre-flight: source/evidence output is consumed by the builder and pairing renderer; use source-prefixed string entity IDs and canonical source keys consistently throughout.
- Task 1: new feature tests failed before implementation, then passed with the source/evidence provider. The Master now works without an import as well as with the historical baseline; canonical aliases do not create phantom native years. Native annual completion reuses the established scheduled-month/week calculator.
- Task 2: all six Master modes, focused presets and source transitions verified with guarded fixtures. Same-year period/department scopes are explicitly linked instead of showing redundant selectors. Search, scope, frequency and department context persist through relevant shortcuts.
- Compatibility ruling: old explicit recorded-period URLs retain `standard()` behavior. One existing provisional-scope test now explicitly requests `comparison_method=year`, because the default route intentionally opens an annual Master inventory containing pending indicators instead of an old records-only table. All original scope assertions remain.
- Architecture refinement: new native comparisons use normalized complete-period evidence, while the recorded provider remains the default-period resolver and legacy URL provider. This prevents old averages of incomplete weeks from becoming full-month improvement claims.
- Review: one independent read-only reviewer identified identity conflicts/ambiguity, parent-container counting, stale target periods, nullable raw changes, navigation scope loss and incomplete department defaults. Each received failing regressions followed by fixes. KPI-coded parent indicators remain defined indicators, while plain parent containers are excluded from summary populations.
- Safety: all browser demonstration writes are confined to a guarded temporary SQLite fixture outside the checkout. Read-only service builds against `plan` logged zero data-changing queries and unchanged source/mapping/performance/objective counts. No migration, dependency, environment, credential or working-data changes.
- Final evidence: PHP 276 tests / 2,391 assertions, zero failures/errors, 2 external-workbook skips (274 passed). JavaScript 21 passed. PHP/JS syntax, scoped whitespace and Pint checks passed. Browser QA passed all six Master modes and six focused presets; K7 historic/native delta +10, reversed -10, future native +5; source refresh, actual-target period transition, pagination/show-all, CSV, graph switching, label bounds, horizontal buttons/drag/keyboard and mobile containment all passed without page errors. Single-KPI charts use compact height rather than giant bars.
- Remaining limits are data readiness, not missing UI: existing annual mappings/directions/units and complete current annual reports are not auto-approved or fabricated. Local service read times are not a guarantee of end-to-end network/browser latency. No deployment or live-server writes were requested or performed.
