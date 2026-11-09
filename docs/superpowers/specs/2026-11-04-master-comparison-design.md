# Complete Master Comparison workspace

Approved intent: the user asked to proceed after agreeing a complete Master with focused shortcuts, two-sided comparisons, imported 2018 EFY / 2025 against 2019 EFY / 2026, and later native fiscal years without a redesign.

- Master supports year, period, department, objective, KPI and actual-versus-target comparisons. Only relevant controls appear; focused tabs are presets, not exclusive entry points.
- Reference and Current have explicit source/year, period, authorized department and objective/KPI selections. Corresponding KPI codes and existing validated relationships are the default. Different entities can be inspected side by side without inventing equivalence or an improvement verdict.
- Sources preserve original imports and native approved plans/results. Year labels are fiscal-start identifiers: EFY plus seven for the existing Gregorian plan convention. Resolve aliases only at this boundary. Never rewrite source/database years.
- Native future annual comparisons reuse annual completeness checks, accountable department selection, stable KPI identities and normalized measurement/direction checks. Annual imports never become fabricated monthly results. Missing values stay missing; drafts and incomplete years are labelled, not official annual deltas.
- Objective comparisons show their component indicators and coverage; no summing unlike units or mixing old/new weights into one executive score. Same-cohort paired averages are separate from each side's available-result overview.
- Preserve source-row evidence, historical administration permissions, graph-type controls, full readable chart labels and panning, pagination, CSV safety, mobile layout and existing deep links.
- No migrations, dependency changes, .env changes, working-data writes, accounts/password changes, new databases or automatic mapping approvals.

Approach: extend the existing comparison boundary with a Master orchestrator, authorized source catalogs and a reusable paired-evidence provider. Existing historical and recorded-period engines remain the shared providers for their established comparison paths; native annual and explicitly selected entity pairs use normalized evidence with the same calculator and annual-completeness service. This preserves existing workflows while adding the missing dimensions. A separate independent report or a repeated giant form in each tab would not meet the agreed Master/shortcut requirement.

Execution: implement in the active Plan checkout, preserving its existing dirty worktree; user explicitly requested improvement in this project. Record steps without committing their unrelated pending edits. Execute inline, verify against guarded SQLite and browser fixtures, inspect working `plan` data read-only, then perform one independent final code review.
