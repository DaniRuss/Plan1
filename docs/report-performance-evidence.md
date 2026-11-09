# Missing and incomplete report performance

Reports and Master Report share the read-only `ReportPeriodEvidence` resolver.
The same rules apply to screen views, report summaries, PDF, Excel and CSV.

| Evidence | Display and scoring |
| --- | --- |
| No scheduled target | N/A; no achievement |
| Scheduled, no performance | Not Reported; actual and achievement absent |
| Entered, awaiting final review | Pending Approval; excluded from official actuals and scores |
| Some required entries approved | Actual progress remains visible, Provisional; no final leaf score |
| Every required entry approved, including entered zeroes | Actual value and direction-adjusted achievement |

Reporting coverage counts completed versus expected reporting entries, separately
from score weights. A zero-weight activity still needs reporting before a period
can be complete. Weekly months require all valid reporting weeks in the selected
fiscal year. Wrong-year and unapproved records do not count.

Quarter and annual totals preserve checkpoint versus sum aggregation. Approved
explicit quarter/annual records are supported without double-counting their
underlying monthly records. Partial annual schedules never produce a final score.

Hierarchy scores use only scored evidence; missing descendants are not assigned
zero achievement and remain in the reporting-coverage denominator. An aggregate
score based on only completed activities is explicitly provisional, not a final
organizational achievement. Existing plans, weights, permissions and workflows
are not changed.

Excel uses strict null handling: a genuine numeric zero remains present while a
missing score is an empty cell. PDF status and coverage text wraps rather than
being clipped. No database migration or data rewrite is required.

## Verification (6 November 2026)

- 17 focused missing-performance tests, including real XLSX and CSV serialization.
- Complete PHP suite: 334 tests, 2 skipped unavailable-reference-workbook tests,
  2,733 assertions, zero failures (332 tests passed).
- JavaScript: 25 tests passed.
- Independent read-only review findings covered by failing-then-passing tests:
  quarterly evidence in annual reports, zero-weight completeness, and preserving
  a saved weekly target when actual performance is absent.
- Live `plan` database checked using a read-only transaction: Operation February
  2026 now has null actual/achievement and Not Reported, not the former 16.54%.
- All 46 partially reported Operation quarter cells remain provisional, with
  accurate coverage and no fabricated final leaf achievement.
- Record counts unchanged; no credentials, database records or settings edited.
