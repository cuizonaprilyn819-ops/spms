# Master integration on November 1 2026

Integrated master commit `a7fb3e1` into `codex/commitment-planning-ui`, starting from `6fcc1fa`. The original histories remain intact. The local recovery bundle, PostgreSQL dump, private storage archive and row-comparison manifest are stored outside the repository under the workstation's SPMS backup directory.

## Reconciliation decisions

- Retain the historical PCR archive and its owner/named-participant access policy, source files, versioned transcriptions and DOCX/XLSX/PDF exports. Operational report-generation history and compliance use the normal reports, not imported archives.
- Incorporate office hierarchy visibility, evidence analysis, duplicate-source handling, period chairpersons, PCR deadlines, reminders and independent performance review from master.
- Preserve the Planning Officer upload workflow: source-document routes remain Administrator/Planning Officer only. Incoming source-document tests now use that authorized role; Campus Directors retain their operational target/report responsibilities. Only administrators can grant the expanded Planning Officer role.
- Preserve existing office assignments when an office becomes inactive or falls outside the current selectable catalogue. New assignments still require an active office in the configured campus.
- Retain Quarter labels, compatible stored semester values, repeatable period setup, Figtree, the deep blue sidebar and accessible icon actions.
- Resolve the Vite project root from its configuration file so builds through a Windows directory junction still produce Laravel-compatible manifest keys.
- Keep notification assertions with an independent reviewer; preparing a report no longer allows self-approval. Target fixtures use the configured campus and reporting dates rather than bypassing the incoming schedule checks.

## Upgrade verification

Restore the database backup into a separately named disposable PostgreSQL database and run ordinary migrations there first. Compare original columns and rows before and after, and verify original PCR hashes. Test fresh migrations and workflows on another disposable database. Never run RefreshDatabase or migrate:fresh against the usable local installation.

The upgrade rehearsal and local additive migration retained all existing records, accounts, historical PCRs and evaluation periods. The selected July–December 2026 period and closed historical periods were preserved. AI and outgoing email stayed disabled. The new reminder email flag was explicitly disabled locally; no scheduler or mail service was started. Reminder delivery was checked with dry-run and synthetic test data only.

The pre-merge PostgreSQL suite contained 215 tests, with 20 errors and 15 failures. Those results are the comparison baseline; do not call the entire suite passing while legacy failures remain. Incoming workflow checks and historical archive checks must be assessed separately from obsolete role and form expectations.

The final full PostgreSQL run contains 261 tests: 244 passing, 7 errors and 10 failures. All 17 unsuccessful cases also occur in the pre-merge baseline. The focused reconciliation run passes 25 tests / 232 assertions, and the final critical-workflow run passes 54 tests / 387 assertions; all 15 JavaScript checks pass. External AI responses and notification delivery are tested with synthetic inputs and fake transports, not live external services. Original-row comparisons exclude only Laravel's normal remember-token rotation during logout.
