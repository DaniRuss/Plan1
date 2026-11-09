# Historical performance comparison repair

## Corrected department self-comparison and flexible periods (2026-11-05)

This is the current behavior, following the user's clarification. It supersedes the cross-department benchmarking/ranking design documented in the earlier checkpoints below.

**Departments:** select ONE department, a Reference year/source and a Comparison year/source. Both annual cards and evidence tables belong to that same department. There are no cross-department pairs, all-department rankings or rank exports. Each year's complete own-department activity population and effective annual weights produce its overall annual score; the difference is Comparison minus Reference in percentage points. Activities/weights that differ between years are disclosed, rather than forcing identical KPI sets or misrepresenting the score as identical-KPI growth.

Both years are independently selectable from available sources, including the original `2018 EFY / 2025` workbook, native `2019 EFY / 2026`, and future native years as their plans exist. Today's date does not constrain the chosen years. Swap reverses the selected years and score difference, keeping the department. No second available year produces an explicit notice, not a self-comparison. Same-year calendar aliases and different departments in an old two-department request are rejected.

**Period over period:** the Reference and Comparison year/source controls are both selectable. Native evidence supports month with month, quarter with quarter, or annual with annual, within or across years. Identical same-year/same-period results and mixed period lengths are rejected. For example, September→October in one year, September across two years and Quarter 1 across two years are supported. Selecting imported annual-only history reconciles both reporting periods to annual without overwriting the other selected year. Selecting native evidence again restores its monthly/quarterly options.

Same-year defaults retain the latest two distinct available periods. Cross-year defaults select the same available period where possible. Complete compatible period pairs use stable KPI identity, the same objective or established relationships; distinct native cross-year records cannot become a final improvement merely because their descriptions match. Available unmatched evidence remains visible. Historical department ownership is resolved from the original source responsibility, not inferred from current-year mappings; shared/unresolved responsibility remains pending.

Visible terminology is **Reference** and **Comparison**, not Current; existing internal URL/data keys remain compatible. Master, focused navigation, refresh, swap, charts, evidence, CSV and responsive layout use the same selections. Bare legacy recorded-period URLs retain their original provider; explicit source selection and current Master/focused controls use the independent-source Workbench. No migration, environment/credential, original source value or approval change is needed or performed.

Verification: full guarded SQLite suite **317 tests / 2,624 assertions**, zero failures/errors, two unavailable external-workbook skips; all **25 JavaScript tests** pass. Private SQLite browser verification covers weighted annual self-comparison (85%→95%, +10pp and reversed -10pp), both source selectors, historical ownership guards, cross-year month +30pp, quarter +10pp, same-year adjacent months +20pp, annual-only imports, native period-option recovery, source/type/focused/Master navigation, CSV, evidence and desktop/mobile layout with no page errors. Fresh independent review's same-year Year-link and cross-year wording-only identity findings were reproduced RED and fixed GREEN. Syntax, Pint and whitespace checks pass.

An earlier read-only build against `plan` verified all six modes with unchanged source counts and zero data-changing queries. The final live recheck could not connect because MySQL stopped accepting connections on `127.0.0.1:3306`; database settings were not changed or the service restarted. All demonstration browser records remain outside the project in temporary private SQLite, never in `plan`.

## Annual department scorecard (2026-11-05)

Department comparison now means **overall annual department performance**, not a match between the departments' KPI lists. This supersedes the department-period/common-KPI behavior described in the older verification checkpoints below. The other five comparison modes keep their existing purpose.

1. In Master choose **Department versus department**, or open **Departments** directly.
2. Select one annual fiscal year/source: imported `2018 EFY / 2025`, native `2019 EFY / 2026`, or a later available year.
3. Show **All active departments**, or choose **Two selected departments**. Search by department name/code and order by final score, name or reporting completeness.
4. Read annual scores, ratings, reporting/weight coverage and readiness. Ranks use all complete active departments before display filtering. Ties share rank; incomplete departments have no final rank. Pair differences are percentage points, not a claim about identical workloads.
5. Open **Activity evidence** for annual target, actual, source row, achievement, effective weight, department share and weighted contribution. Annual CSV exports the displayed department population, including missing-value/readiness fields.

Each department keeps its own eligible activity population. Its score is the sum of capped annual achievement times effective annual weight divided by total scoring weight. It does not require matching activity codes, sum unlike units, or equally average activity percentages. Non-scoring children do not suppress a valid parent; genuine scoring descendants replace containers so they are counted once.

Native evidence uses approved departmental plans and final reviewed explicit annual results, or a fully reconciled annual reporting schedule. Approved annual weight allocations remain authoritative. Duplicate approved objective records, missing weights, missing or partial results prevent a final score; explicit zero remains valid. Carried monthly, frequency, KPI-search and provisional-status filters cannot narrow the annual scoring denominator.

Historical evidence preserves original annual percentages, values, weights, source rows and responsibility. Blank responsibility may inherit from a real ancestor, never an unrelated previous row. Shared, partly unknown or `All departments` responsibility is shown as contextual evidence, not copied into independent departmental actuals. Current KPI mappings cannot manufacture historical departmental ownership. Unknown year aliases and unauthorized sources/departments are rejected. No source values, approvals, accounts, schema or environment settings are changed.

Verification: **304 PHP tests / 2,535 assertions**, zero failures/errors, two unavailable external-workbook tests skipped; **22 JavaScript tests** pass. Private-SQLite browser QA covers native weighted pair totals (85% versus 60%), shared historical evidence staying pending, complete exclusive historical evidence, source/scope/swap transitions, Master/focused navigation, department-pair to period transition, all six Master modes, pagination, CSV, evidence expansion, seven-column widths and mobile containment. No JavaScript page errors. Independent review's ancestor-selection, cross-mode department-selection and missing code projection findings have regression coverage and fixes.

Read-only `plan` verification found **30 active departments**. Native 2019 EFY / 2026 has no final annual department totals yet (24 without annual data, five awaiting annual evidence, one partial). The original 2018 EFY / 2025 workbook has 161 shared/unresolved responsibility warnings, so its final departmental totals remain pending; original source achievements remain visible in the activity evidence. Neither missing annual reports nor historical attribution were automatically approved. All six modes built successfully; annual service builds took approximately 0.15–0.31 seconds in this check. Source counts stayed unchanged and the query monitor recorded **zero data-changing queries**. Browser demonstration fixtures exist only in temporary SQLite outside the project, not in `plan`.

## Complete Master workspace (2026-11-04)

The Master now contains the complete comparison builder. The focused navigation entries are shortcuts, not the only place a comparison can be configured.

1. Open **Performance Comparison → Master overview**.
2. Choose **Year versus year**, **Period versus period**, **Department versus department**, **Objective versus objective**, **KPI versus KPI**, or **Actual versus target**.
3. Set the Reference and Current sources and the relevant period, department or indicator. Changing a source or department refreshes authorized indicator choices. Selecting a different Reference indicator clears the old Current selection so an existing relationship or stable KPI code can find its counterpart.
4. Click **Compare selections**. Read targets, actuals, achievement and the explanation beside each pair. Expand **Source & calculation evidence** for the workbook row, original descriptions, uncapped achievement and reporting completeness.
5. Use **Swap sides** where meaningful, shared filters in Master, chart controls, pagination and CSV. Focused views retain applicable shared filters and department scope without repeating the full Master settings form.

Reference and Current years are independent in year, objective and KPI comparisons. For example, select imported `2018 EFY / 2025 · K7` versus native `2019 EFY / 2026 · K7`. Later approved native plan years appear automatically; native `2020 EFY / 2027` versus `2019 EFY / 2026` uses the same comparison boundary. No new historical Excel import or hardcoded year rollout is needed for native results.

Period comparisons use one native fiscal year and department, with two distinct months or two distinct quarters. Their labels explicitly name the two periods. Department comparisons use one fiscal year and reporting period with two different authorized departments; initial selections prefer departments with approved plan evidence. A missing second department produces a notice, never an organization-wide substitute. Actual-versus-target derives the benchmark source, period and scope from Current on the server, and labels results Above/Below/On target rather than claiming year-on-year improvement.

Objective groups compare their component indicators, not unlike-unit totals. Plain parent containers remain selectable but are excluded from summary populations. Separately defined KPI-coded parent indicators remain defined indicators, consistent with the historical source. Duplicate or conflicting KPI identities do not automatically become equivalent: full-population uniqueness or an explicit existing relationship is required. Even a manually selected pair stays context-only when its definitions, units or directions conflict. Existing draft/changed relationships are not approved through this screen.

Annual evidence always requires final reviewed results and a complete reconciled reporting schedule, or an explicit approved annual result. Available partial progress remains visible but cannot generate a final annual improvement. Monthly and quarterly evidence also marks incomplete scheduled reporting, including missing reporting weeks. Imported annual figures are never substituted for months. Explicit zero remains a valid result. Percentage-only historical evidence can support an achievement comparison without inventing missing raw actual or target changes.

Implementation: `ComparisonWorkbench` resolves scoped sources, selections and matched populations; `ComparisonEvidence` normalizes read-only historical/native evidence with the established calculator and annual-completeness service. The existing historical annual provider retains source editing and mapping administration. Existing recorded-period deep links remain supported. No new dependencies, migrations, environment changes, database writes, credential changes or automatic mapping approvals are involved.

Verification: the full guarded SQLite PHP suite passes **276 tests / 2,391 assertions**, with two external-workbook tests skipped (274 passed); **21 JavaScript tests** pass. Browser QA uses only a private loopback SQLite fixture and covers six Master modes, six focused presets, renamed K7 matching, native future-year matching, side swapping, source refresh, target-period transitions, pagination, CSV, graph switching, complete chart labels/panning and mobile containment. A read-only check of all six modes against `plan` issued zero data-changing queries, retained source counts and built each mode in approximately 0.1–0.6 seconds in that check; this measures service build time, not a guarantee of complete browser load time.

Independent review findings received regression tests: conflicting codes with identical wording, ambiguity on either side, container double counting, stale benchmark periods, null raw arithmetic, preset scope/search loss and missing department defaults. The existing working database still has no complete validated annual pairs; the screen shows readiness reasons instead of creating numbers or changing those records.

The sections below document the preceding implementation and its earlier verification checkpoints.

The existing approved historical-comparison design is implemented in the active `Plan` checkout. No import values, passwords, objectives or live performance records are rewritten by this change.

## Fiscal-year identities

The project owner's convention is **2018 EFY / 2025 baseline** and **2019 EFY / 2026 current plan** (Gregorian fiscal-start identifiers, not day-level calendar conversion). Historical imports keep their original EFY year. A historical request for `historical_year=2025` resolves the existing `2018` source. A current request for EFY `2019` uses stored plan `2026` when that populated plan year exists. The UI displays both labels.

## Readiness and calculations

- Opening the comparison defaults to the master overview of the imported annual baseline when one exists. Year-over-year also resolves the imported EFY/Gregorian reference and uses annual evidence; period comparisons use native recorded periods only.
- Historical target, actual, reported achievement, workbook name and row remain visible even without a current match or result.
- Reported historical achievement is retained; the calculator supplies it only when no reported percentage exists. Scored achievement is capped at 100%; uncapped values remain in evidence.
- Current approved annual records override monthly records and are counted once. Otherwise all planned months must be approved, and their targets must reconcile to the annual schedule. Weekly/daily reporting also requires all expected reporting weeks.
- Organization comparison uses the accountable department, not the sum of repeated company-wide KPIs. An explicit department filter shows that department's authorized records.
- Draft mappings, changed/split/merged definitions, missing or conflicting units, unconfirmed direction, and incomplete years are context, not a fabricated annual delta.
- Matched summaries use the same complete KPI pairs and equal weighting in both years. They are not the executive weighted scorecard. Historical overview coverage is explicitly separate from matched-pair comparison.
- All filtered rows can be viewed, paged or downloaded as CSV. Missing values remain blank. Text cells are protected from spreadsheet formula injection.

## Implementation rulings

- Repair the existing checkout rather than isolate an unrelated copy: the user explicitly requires changes in the opened project, and the worktree contains their pending changes. Preserve unrelated edits; do not commit them.
- Keep the original locked source instead of renaming database years or creating a second baseline. Year equivalence belongs at the comparison boundary.
- Preserve annual-vs-annual fairness rather than divide annual history into invented months.
- Render a single selected comparison layout and load only the selected KPI mapping editor. The previous nested conditional layout rendered the historical page twice and repeated all objective selectors.

## Verification

Regression coverage: `tests/Feature/HistoricalComparisonWorkspaceTest.php`, plus existing historical, dashboard and comparison-method tests. All tests use the guarded in-memory SQLite test database. Browser QA uses the private loopback-only documentation fixture, never the working `plan` database. A separate read-only service build checks the real `plan` source and labels.

Verified 2026-11-03: full PHP suite 220 tests, 2,059 assertions, zero failures/errors, two skipped external-workbook tests (218 passing). Comparison-specific regression file: 18 tests, 70 assertions. Browser checks: HTTP 200, one rendered application layout, no JavaScript page errors, functioning pagination/show-all/CSV download, and no mobile horizontal page overflow. Existing graph-selector JavaScript tests: five passing. PHP and JavaScript syntax checks passed.

Read-only working-database check: `plan`, original `2018 Action plan draft 3.xlsx`, baseline `2018 EFY / 2025`, current `2019 EFY / 2026`, 255 eligible historical indicators, 249 with actual values, 27 approved links. No complete validated annual pairs are currently available; the interface explains pending measurement and result readiness rather than fabricating deltas.

Independent review findings were fixed and regression tested: non-null zero month targets require reports; a selected year's mapping editor cannot reuse another year's approved link; null-objective retired/non-comparable links remain visible as contextual relationships. Existing historical-read permissions and mutation workflows are retained, not broadened. The current-only activity inventory is not expanded into a second report; the historical comparison remains source-led.

## Focused comparison views (2026-11-04)

The master overview owns broad filtering, reporting frequency, review-status preferences, movement, sorting, source inspection and KPI relationship management. Focused views do not repeat its full filter form:

- **Year over year:** current year, reference year/source and department. An imported `2018 EFY / 2025` reference selects the existing annual comparison engine and preserves the workbook evidence. An old monthly query cannot silently manufacture monthly historical results. Native references without an import retain their same-period comparison.
- **Period over period:** plan year, two distinct months or two distinct quarters, and department. The default is the latest two recorded periods in the authorized result scope, not `all` versus `all`. Insufficient data leaves the reference unset. Identical periods, all-month self-comparisons and mixed month/quarter pairs are rejected.
- **Departments:** plan year, reporting period and two distinct authorized departments. The initial selection prefers departments with results; unrelated activities remain unmatched, not fabricated pairs.
- **Actual vs target:** plan year, reporting period, department and activity. The benchmark stays at 100%; uncapped achievement preserves overperformance.

Shared frequency, review scope, movement and sorting follow navigation into reporting views; activity selection is visible and clearable. Annual comparisons always use final reviewed results, regardless of the provisional preference selected for the focused reporting views. Changing review scope preserves the selected period pair and departments. Fiscal-year aliases cannot be used to compare a year against itself.

All views share the same presentation, source cards, paired chart, explained missing values, activity evidence, pagination and CSV controls. The old repeated comparison-method dropdown and unrelated filter fields are removed from the focused forms. No schema, environment, credentials, source workbook values or working database records change.

Verified after the focused-view changes: full guarded SQLite PHP suite **247 tests / 2,262 assertions**, zero failures/errors, two external-workbook tests skipped (245 passing); all ten JavaScript tests pass. Browser QA of all five views returned HTTP 200 with one layout, no JavaScript page errors and no mobile page overflow. Focused form submissions, chart switching, pagination, CSV, shared-filter navigation and period-preserving review toggles passed. Browser fixtures remain outside the project in temporary SQLite; no demonstration results were inserted into `plan`.

Read-only `plan` verification confirms year mode uses `2018 Action plan draft 3.xlsx`, `2018 EFY / 2025` reference versus `2019 EFY / 2026`, with baseline average 93.45% visible and incomplete annual pairs still pending. Period mode independently selects October versus September (23 activity rows), rather than comparing all results with themselves. Independent review's shared-filter access, scope-toggle selection and same-fiscal-year alias findings were fixed and regression tested.
# Composite KPI evidence

Performance Comparison distinguishes direct measurements from weighted activity-based KPIs. A container without a substantive direct measurement derives its score from eligible terminal activities. For the imported K22 example, the workbook's 13 activities contribute 4.4705 of 5 points, or 89.41% weighted achievement. This is not a measured digitalization percentage.

The KPI comparison and historical annual overview expose an expandable activity breakdown, original targets/actuals/units, weights, points and source rows. Missing results or weights remain unavailable; partial points are provisional. Composite headlines are excluded from flat leaf averages to prevent double-counting. A changed component set, unit, definition or relative weighting prevents a like-for-like improvement claim. Each year's available result remains visible as context. Comparison CSV includes explicit composite point fields; focused composite exports include component rows.

These calculations are read-only and limited to Performance Comparison. No objective, report, workflow or imported source record is modified.

