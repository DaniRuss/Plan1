# Missing report performance

Approved behavior: absent performance is Not Reported, an explicitly entered zero
is a valid result, incomplete periods are Provisional with accurate coverage, and
unscheduled targets remain N/A. Apply this to Reports, Master Report and exports.

Use shared read-only period evidence rather than hiding zeroes in templates:
template-only changes cannot distinguish missing values from real zeroes or fix
incorrect totals. Do not change the global PlanItem numeric helper, since other
screens rely on it; adapt the report consumers instead.

Only final-reviewed, non-null evidence counts as official performance. Count the
actual expected monthly/weekly slots for coverage. Keep partial actuals visible,
but do not assign them a final achievement or failure status. Hierarchy totals
use scored evidence only, identify incomplete results as provisional, and never
count a missing child as a zero. Preserve existing targets, weights, entered
zeroes, review workflows and lead-department consolidation.

No migrations, configuration changes or edits to existing database records.
Implement in the existing Plan checkout and preserve unrelated working changes.
