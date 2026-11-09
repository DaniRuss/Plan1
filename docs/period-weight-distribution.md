# Period weight redistribution

Users enter extra allocations, not final effective weights. Each planned sibling retains its original weight. The sum of extra allocations must equal the original weight of unplanned siblings, within a tolerance of 0.0001. Final effective weight is original plus extra; this final number remains the value stored for scoring. Negative allocations and allocations above the available amount are rejected.

Planning includes an explicit zero target. A calculated parent with a blank target is planned when an included, scorable descendant has a target in the selected month or quarter. Manual-override parents require their own target. Referenced/excluded children do not make the parent planned. Each sibling group is allocated independently; child weights are not added a second time to the parent group.

Example: parent activity 1.1 has original weight 3 and two planned children weighted 1.5 each. Another sibling has weight 2.5 and a target, and one sibling with weight 0.2 has no target. Only 0.2 is redistributed at the parent level. Giving 0.1 extra to each planned parent-level activity yields effective weights 3.1 and 2.6. The child group retains its own original total of 3.

All siblings planned: keep original weights. One sibling planned: automatically add all unplanned weight to it. No siblings planned: no distribution. Approved allocations are read as stored, including planning status; they are not recalculated by this change. Locked distributions must be returned or formally reopened before editing, including by administrators. Existing draft/reopened allocations appear as extra = max(0, saved effective minus original) and must be revalidated before submission.

Deployment: upload PeriodWeightNormalizer.php, PeriodWeightDistributionController.php and resources/views/period_weights/edit.blade.php together. No database migration. Clear compiled Blade views after upload. Proportional calculations are unchanged.
