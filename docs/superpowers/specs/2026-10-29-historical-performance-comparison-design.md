# Historical Performance Comparison Design

## Intent

Add an auditable annual comparison between the supplied 2018 EFY action-plan results and final approved 2019 EFY performance without weakening the existing year, period, department, and target comparisons.

## Goals

- Import 2018 annual KPI results as a locked historical source.
- Correct the confirmed numbering sequence (`11.20`, `13.7` through `13.13`, and `14.10`) during import.
- Preserve a missing unit as unspecified and allow an authorized later correction with audit history.
- Map historical KPIs to current strategic objectives through reviewed database mappings.
- Require expert preparation and director/head approval before a mapping contributes to official comparisons.
- Compare approved mappings only, display target, actual, achievement, percentage-point change, actual change when compatible, and coverage counts.
- Keep missing, new, retired, split, merged, unit-changed, and definition-changed KPIs visible rather than converting them to zero.

## Non-goals

- Do not infer monthly 2018 results from annual values.
- Do not overwrite existing plan, objective, or performance records.
- Do not automatically approve fuzzy matches.
- Do not mix historical and current weights into one official score when structures differ.

## Constraints

- Initial comparison is final annual 2018 versus final annual 2019.
- Raw actual change is valid only when normalized unit and KPI definition are compatible.
- Existing comparison methods remain available.
- Existing uncommitted user changes must be preserved.

## Approach

Use dedicated historical import, KPI, and mapping tables. The workbook importer normalizes known unit aliases and confirmed code corrections while preserving source rows and reported achievement. A mapping-review workflow links historical KPIs to current strategic objectives and records comparability, mapping type, confidence, comments, preparer, reviewer, and timestamps. A separate annual comparison service consumes only approved comparable mappings and final approved current performance.

Alternatives rejected: exact text matching has negligible coverage; matching by code is unsafe because numbering changed; embedding mappings in application code is unauditable and difficult to maintain.

## Authorization

- Plan and Report Expert, Directorate, Head, and System Administrator may view historical data and mappings.
- Expert and higher roles may import, edit missing units/directions, prepare mappings, and submit them.
- Directorate, Head, and System Administrator may approve or reject mappings.

## User experience

The Performance Comparison page gains a Historical Annual method with summary cards, matched-coverage information, movement and mapping-type filters, a chart, and a detailed table. Authorized planning users also receive Historical Data and KPI Mapping management panels on the same page, including workbook upload, source issues, editable unit/direction fields, mapping forms, and approval actions.

## Calculation rules

- Historical achievement uses the reported percentage when present; otherwise calculate it from target, actual, and direction.
- Current annual target and actual use the current plan item's annual aggregation rules and only final approved performance.
- Achievement change is current achievement minus historical achievement in percentage points.
- Actual and target changes are shown only for approved direct/renamed mappings with compatible normalized units and definitions.
- Achievement is capped at 100% for official comparison; uncapped achievement is shown separately.
- Missing data remains null and is excluded from averages.

## Audit and safety

Every import, unit/direction edit, mapping submission, approval, and rejection writes an audit log. Source filename, checksum, row number, original code, and original values remain available. Imported historical rows are never silently rewritten.
