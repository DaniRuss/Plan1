# Historical import value recovery

Scope: historical comparison imports only. No current-year objectives, plans, performance, approvals, users or credentials change.

1. Reproduce formatted-duration loss and other numeric-format cases with isolated SQLite/temporary workbook tests.
2. Read calculated numeric values without display rounding. Convert numeric Excel elapsed-time serials and textual durations to the declared time unit; preserve percentage display scale, explicit zero and genuine blanks.
3. On identical source checksum re-upload, backfill only missing numeric fields of matching existing source rows and equivalent units. Preserve non-null values, row counts, codes, mappings, measurements and review states. Audit changes and report counts. Skip mapping suggestions for this repair path.
4. Back up historical tables in the local `plan` database, repair only verified missing values, reconcile all imported rows against source, verify idempotence, run regression suites and prepare the same safe browser re-upload path for the testing server.

Evidence: original workbook hash matches existing 2018 import. Scan262 rows found exactly2 missing source values: E20=72:00:00, F20=86:06:00 for activity2.2. Excel raw values are3 and3.5875 days, respectively. Reported83.6% remains unchanged.
