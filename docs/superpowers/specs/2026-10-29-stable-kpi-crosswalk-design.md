# Stable K1 to K27 Crosswalk Design

## Intent

Use the 27 permanent KPI identifiers in the 2018 final annual plan as the stable semantic link between differently worded 2018 and current plan activities.

## Constraints

- Update only: do not delete or recreate objectives, imported historical rows, plans, results, users, or existing mappings.
- Preserve activity codes, descriptions, units, targets, actuals, status, and ownership.
- Assign each identifier K1 through K27 exactly once to a selected 2018 row and exactly once to a selected current objective.
- Treat identical permanent-code pairs (`K1` to `K1` through `K27` to `K27`) as approved, comparable mappings because the permanent code is the reviewed semantic identity. Description-only suggestions remain drafts for Plan and Report review.
- Prefer the activity with the same measurement concept. Where the KPI is an organization-level calculated measure, attach it to the closest main objective.

## Approach

Add a nullable `kpi_code` field to `historical_kpis`; the current `strategic_objectives` table already has this field. Store the reviewed crosswalk in a focused service, validate all 27 codes and both endpoints before any update, then perform assignments and approved exact-code mapping upserts in one database transaction. Matching and mapping suggestion logic prioritizes identical permanent KPI codes over activity numbering or wording.

Alternatives rejected: overwriting activity `code` would destroy plan hierarchy; description-only matching already produced uncertain suggestions; automatically approving description-only mappings would bypass the established review process.

## Crosswalk

| KPI | 2018 row | Current objective | Meaning |
|---|---|---|---|
| K1 | 2.1 | 2.1 | Train punctuality OTP |
| K2 | 4.12 | 4.11 | On-time delivery rate |
| K3 | 12.1 | 13.3 | Customer satisfaction rate |
| K4 | 2 | 2 | Freight train load ratio within operational efficiency |
| K5 | 3.4 | 3.4 | Seat occupancy rate |
| K6 | 1 | 1 | Freight ton-km within total freight transport |
| K7 | 4.14 | 4.13 | Cargo damage or loss rate |
| K8 | 1.12 | 1.15 | National cargo share |
| K9 | 3 | 3 | Passenger-km within passenger transport |
| K10 | 5.4 | 6.4 | Maintenance time efficiency |
| K11 | 6.6 | 7.6 | Locomotive fleet utilization |
| K12 | 10.6 | 11.7 | On-time project completion |
| K13 | 6.7 | 7.7 | Rolling stock availability or functionality |
| K14 | 5 | 6 | Infrastructure availability |
| K15 | 5.1 | 6.1 | Track Quality Index |
| K16 | 5.2 | 6.2 | OCS quality excellence |
| K17 | 15 | 16 | Revenue growth |
| K18 | 16.1 | 17.1 | Net profit margin |
| K19 | 16.2 | 17.2 | Operating ratio or cost optimization |
| K20 | 10.7 | 11.8 | Project cost variance |
| K21 | 13.3 | 14.2 | Procurement cycle time |
| K22 | 14 | 15 | System automation |
| K23 | 4.18 | 15.2 | Real-time cargo tracking in the integrated transport system |
| K24 | 7 | 8 | Safety leading and lagging indicators |
| K25 | 8 | 9 | Security incident rate |
| K26 | 11 | 12 | Employee retention rate |
| K27 | 11.12 | 12.8 | Employee productivity and performance appraisal |

## Verification

- Database contains exactly 27 distinct K codes on the selected historical import and 27 on current objectives.
- Both sides contain every value K1 through K27 once, with no duplicates or gaps.
- Existing non-KPI fields are unchanged.
- Twenty-seven direct approved mappings connect identical permanent codes; uncertain description-only suggestions remain drafts.
- Existing comparison and workbook-import tests remain green.
