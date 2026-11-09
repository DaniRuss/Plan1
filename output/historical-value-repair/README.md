# IPRPMS historical comparison value repair

This update is limited to historical Performance Comparison imports. No migration, new database, reseeding, password reset, or terminal command is needed.

## Deploy through cPanel

1. Back up your testing-server files and database first.
2. Upload and extract this ZIP into the Laravel project root (the folder containing `app`, `resources`, and `routes`), allowing the four included project files to replace their existing versions. Do not extract it into `public` alone.
3. Do not replace `.env`, `storage`, or the database with local copies. They are not included in this package.

## Restore missing historical values in the browser

1. Sign in as a System Admin or an authorized Plan and Report user.
2. Open Performance Comparison, then Master overview.
3. Expand **Historical data administration** near the bottom.
4. Set **Source Ethiopian fiscal year** to **2018**.
5. Select the **unchanged original workbook** previously imported (`2018 Action plan draft 3.xlsx`). Do not open and re-save or edit it first; that can change its checksum.
6. Click **Repair missing values**. Do not use **Import & validate new source** for this repair.
7. Read the result message. If the workbook checksum or fiscal year does not match an existing import, the repair is rejected and nothing is changed. If rows have altered codes, descriptions, or units, those rows are skipped.
8. Verify activity **2.2**: target **72 hours**, actual **86.1 hours** (86 hours, 6 minutes), historical achievement **83.60%**. The Excel display `86:06:00` is elapsed time, not the decimal number 86.06.

The testing server may have a different repair count from localhost. Check its result message; this package does not assume the live database is identical.

## Preservation rules

- Only NULL weight, annual target, annual actual, or reported-achievement fields are restored from matching source rows. Genuine workbook blanks remain blank.
- Existing values, including explicit zeroes, are never overwritten. Existing rounded values are preserved as well.
- No new historical rows or sources are created by **Repair missing values**.
- KPI codes, direction confirmations, mappings, approvals and review decisions are not reset, re-suggested or approved.
- Current-year objectives, plans, performance, users, departments and credentials are untouched.
- Repairs are audited; repeating the same repair produces zero additional changes.

## Included project files

- `app/Services/HistoricalPerformanceWorkbook.php`
- `app/Services/HistoricalPerformanceImporter.php`
- `app/Http/Controllers/HistoricalPerformanceController.php`
- `resources/views/performance/_historical-management.blade.php`

The parser reads calculated numbers without display-rounding losses, converts Excel elapsed-time values into their declared unit, respects percentage formats, and distinguishes zero from missing data. It uses the applicable number-format section and rejects calendar dates as measurements.

Local validation repaired two missing values in one historical activity after scanning all 262 source rows. Local backup: `storage/app/private/historical-value-repair-20261107/historical-comparison-before-repair.sql`. This backup is private and deliberately excluded from the ZIP. The testing server has not been accessed or modified by preparation of this package.
