# Department self-comparison and independent reporting years

## Approved intent

The user clarified and approved: Departments compares ONE department's overall annual performance across two freely selected fiscal-year sources, never different departments or cross-department rankings. Period over period allows independently selected years, month with month or quarter with quarter within or across years, and annual with annual. Imported 2018 remains annual-only. Reference and Comparison replace Reference and Current in visible comparison terminology. A year selection is never tied to today's year.

## Approach

Replace the previous all-department/pair scorecard with a single-department, two-source annual workspace, reusing AnnualDepartmentEvidence and existing annual weighting/completeness rules. Expand the existing period Workbench rather than creating a parallel reporting engine. Cosmetic unlocking alone would leave server and browser source synchronization wrong; keeping department benchmarking would contradict the clarified task. Implement directly in the existing Plan checkout, as requested throughout this project.

## Behavior

- One authorized active department; two independent available sources with dual EFY/Gregorian labels. Default to a department with evidence and an available earlier annual source; no invented reference if only one year exists. Same fiscal year aliases are not a year-over-year comparison.
- Reference and Comparison annual cards have scores, rating, reporting/weight coverage, original activity evidence and source responsibility warnings. Delta only for two final complete scores. No departmental rank. Changed populations/weights get a visible interpretation warning, not a fabricated same-KPI claim or forced mapping requirement.
- Whole-department annual denominators ignore stale KPI/frequency/search/provisional filters. Native own-department activity records and original historical responsibility remain distinct. No attribution or approvals are invented.
- Periods have independent Reference/Comparison source selectors and a shared department scope. Native sources offer months, quarters and annual; imported sources offer annual only. Equal period lengths; identical year/period/scope rejected, but September across different years allowed.
- Changing source refreshes period choices without overwriting the other selected year. Selecting annual-only history reconciles both sides to annual. Switching back to native allows months/quarters. Defaults retain the existing latest-two-month behavior within one year; across years default to the same available period where possible and show missing evidence honestly.
- Master and focused navigation, source refresh, swap, CSV, charts and mobile work with the same selections. User-facing labels say Comparison, internal URL/data keys remain compatible.

## Constraints and exclusions

No schema, environment, credentials, live data, source workbook or approval changes. Tests use guarded in-memory SQLite; browser fixtures remain temporary/private SQLite. Preserve unrelated dirty work. No commits, branch switches, deployment or separate project. Existing KPI/year/objective/target calculation guards remain in force.

## Validation

Hand-calculated historical/native and future native annual self-comparisons; changed activity weights, incomplete and shared attribution; forbidden different departments/same-year aliases; independently selected month/quarter years and same-year adjacent periods; annual-only and mismatched-granularity guards; source/swap/navigation behavior; PHP/JS suites, private browser and read-only plan checks.
