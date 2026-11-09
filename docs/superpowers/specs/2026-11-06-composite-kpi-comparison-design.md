# Composite KPI comparison

Implement the user's approved distinction between directly measured KPIs and weighted activity-based KPIs. K22 is the motivating case: historical objective 14 has 13 activities whose weights sum to 5 and whose weighted achievement sums to 4.4705 (89.41%). The blank main-row target and actual are not evidence that these results are absent.

## Approach

Use read-only, derived comparison evidence, not database backfills or invented main-row actuals. Preserve direct measurements and manual overrides. A weighted container without a substantive direct measurement gets a composite score from eligible terminal activities; nested containers are never added to their own detail. Native annual weights use the existing allocation resolver. Historical reported achievement remains authoritative where supplied.

The comparison labels composite figures as weighted achievement and points earned / available, never as a physical digitalization percentage. It exposes every authorized contributing activity with its original target, actual, unit, weight, achievement, weighted contribution, source row, completeness and exclusions. Shared historical responsibility is labelled as a joint KPI result, not an independently attributable department score.

## Safety and comparison rules

- No schema, credentials, account, objective, mapping or performance records are changed.
- Existing department access rules remain in force; filtered groups identify partial coverage rather than claim whole-objective completion.
- Missing, partial, unreviewed, unknown-weight or unconfirmed calculated evidence cannot become a final result. Explicit zero remains valid.
- Leaf contributions are capped at 100 percent for scorecard points. Retain uncapped achievement separately.
- Directly measured KPIs keep their current raw target/actual behavior.
- A composite and a direct measurement are different measurement bases; no improvement is asserted between them.
- Across years, show changes in component count/definitions/relative weights. Publish like-for-like composite change only with an unambiguous approved complete component correspondence and equivalent relative weighting. Otherwise show both scores as context, not fabricated improvement.
- Do not count a composite together with its descendants in aggregate summaries.
- Keep raw targets and actuals null for derived composite records; exports carry explicit composite score fields and activity detail.

## Alternatives considered

Copying Excel's rounded main total into actual would confuse weighted points with a physical KPI and become stale. Showing only leaf rows would conceal the requested K22 headline. The selected approach combines a traceable weighted headline and expandable detail using the existing PHP/Blade comparison screen.

## Verification

Use isolated SQLite regression tests, full PHP/JavaScript suites, read-only checks against local `plan`, and desktop/mobile visual checks. Verify the original workbook's formulas without modifying it. Preserve the existing dirty checkout and all unrelated changes.
