# Annual department performance comparison

Approved design: the user accepted the annual-only department scorecard proposal and explicitly asked to proceed. This document records that agreed design; it does not expand it into a new reporting module.

## Intent and scope

Departments compare overall annual results despite having different activities. Replace the department mode in both Master and its focused shortcut; preserve all other comparison modes. Annual fiscal year/source plus all departments or a selected pair are the controlling inputs. No week/month/quarter or activity/frequency filters may change these annual totals.

## Approaches

- Selected: reuse existing annual achievement, effective weights and corporate rating rules, with a read-only source adapter and department scorecard. It preserves the organization's weighting and supports the imported annual baseline and later native years.
- Rejected: equal activity averages distort importance and depend on how activities were subdivided.
- Rejected as the primary department view: matching common KPIs omits departments' distinct responsibilities. The existing KPI comparison remains available separately.

## Calculation and source rules

- One normalized weighted annual achievement per department, capped at 100 for scoring, with uncapped evidence separate. Reuse `EdrPerformanceCalculator`, `PeriodWeightNormalizer`, `HistoricalAnnualResult`, `PerformanceLevel` and existing performer-name matching.
- Native sources use only final reviewed evidence from approved plans, with each department's own records, no sum of organization-wide repeated KPIs. Explicit annual results override constituent months. Otherwise require a complete reconciled annual schedule, including all required reporting weeks. Parent containers and descendants never count twice; reference-only/excluded indicators are contextual. Duplicate accountable records require review, not arbitrary selection.
- Historical sources preserve reported annual achievements, weights, units, source filename and row. Derive a percentage only when actual/target and confirmed direction allow it. Resolve original performer names/codes/established aliases; blank performers may inherit a real ancestor's responsibility, never the preceding unrelated row.
- A historical shared/mixed/unresolved performer assignment cannot become independent departmental performance without an existing department-specific allocation. Show the shared source as context; withhold the affected final department score. Do not retroactively use current-year mappings as proof of historical responsibility. Global unassigned-source warnings remain visible.
- Missing reports or weights are not zero and are not silently dropped to improve a score. Scores and ranking require complete scorable evidence; show reviewed activity/weight coverage and pending reasons. Explicit zeros are valid. No-data departments stay visible as no data.
- Use consistent corporate rating rules. Rank complete departments only, with tied scores sharing rank. Difference is percentage points, not an activity-matching improvement claim. Pair comparison does not require any shared KPI identities.

## Presentation

Annual-only controls in Master and focused Departments: fiscal year/source, all versus two departments, department search and sort. An annual chart, scorecards/table, source/readiness labels, weighted/reporting coverage and expandable department activity details explain each total. Include safe CSV and mobile containment, preserving existing readable chart/panning behavior. Searching departments changes visible rows only, never an annual score or its source population.

## Constraints and implementation decisions

Keep changes in `C:\xampp\htdocs\Plan`, the existing active checkout; the supplied Plan&report path does not exist. Preserve the user's dirty changes. Do not switch worktrees, commit their work, change environment/schema, add dependencies, approve allocations/mappings, or write working data. Browser fixtures, if needed, remain private temporary SQLite only. User's instruction to proceed approves executing this already-discussed design inline.

Native department records are scoped departmental evidence even when a KPI is shared organizationally; the company's consolidation method is not a reason to substitute another department's result. Historical totals require explicit historical responsibility instead. Plain containers are excluded using actual ancestry, not just a code's number of dots. Existing aliases require cycle safety when their target department is missing.
