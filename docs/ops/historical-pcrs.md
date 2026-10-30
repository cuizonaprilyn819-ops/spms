# Historical PCR archive

Open **My Reports → Historical PCRs**. Owners see their own forms; named reviewers see Related PCRs. Administrators can upload and review transcriptions. All source, page-image, correction and export routes share the same policy, including `/api` routes.

Sources are private, immutable PDFs (25 MiB / 100 pages maximum). The administrator selects the last main-form page; remaining pages are evidence annexes. Configure `PCR_PDFINFO` and `PCR_PDFTOPPM` to absolute Poppler executable paths on the server. Rendering runs with a timeout and argument arrays, without shell interpolation. Install PHP GD, ZIP, XML and mbstring for Office exports. Ensure PHP upload/post limits are at least 25M/32M, max_input_vars is 30000, and memory_limit is 512M. The local launcher supplies these process settings.

Transcriptions preserve literal source ratings and separately parse readable numeric values. Nothing recalculates an official rating. Draft exports carry review status and unresolved notes. Verification locks a revision; corrections create a new draft revision. Institutional sign-off dates, imported dates and transcription verification dates are distinct. Original signatures remain in the original PDF; generated form sign-off areas contain names and roles.

Archived PCRs have no fabricated parent commitments and do not enter operational planning totals. Importing into a closed historical period does not open it or change the current period. Back up both PostgreSQL and `storage/app/private/historical-pcr` before maintenance. Do not delete accounts referenced by retained history.

## Private demo import

The local-only `spms:import-historical-demo` command accepts a private JSON manifest, `--password-file`, and an existing `--administrator` email. Keep all three inputs outside version control. It assigns Employee only to newly created demo accounts. Local-demo provider identities preserve user IDs even if emails later change. Existing email collisions fail instead of linking or resetting another account. Each source is committed separately so interrupted imports can be rerun; identical sources retain passwords, revisions and IDs. A changed source for the same owner, period and type is rejected for deliberate review.

Manifest version 1 contains `period` (year, semester compatibility value), `people` (key, name, .test email), and `reports` (owner key, type, absolute source_path, form_pages, participants with person keys/source_role, snapshot). Snapshot fields are office, owner_position, form_date, reported_overall, adjectival_rating, notes, rows, signatories and page_text. Rows contain page, section, objective/activity/indicator/budget/accountable/accomplishment, literal q/e/t/a, remarks and review_note. See the archive validator for bounds. Do not package personal transcriptions or sources as public fixtures.

## Future Google SSO

No Google login is enabled. Ownership uses users.id. `user_identities` has a unique provider/issuer/subject tuple; email is metadata and must never automatically link accounts. The `local-demo` provider is an import identity, not an authentication provider. Future Google integration must explicitly link an authenticated or administrator-approved existing account, validate issuer, audience, expiry, state/nonce and required verified-domain policy, then use the existing Laravel session guard. Provider links never assign roles. Preserve the local administrator fallback. Real university domains and OAuth credentials require separate configuration.

## Verification

Run HistoricalPcrArchiveTest and EvaluationCycleSetupTest against disposable PostgreSQL. Use PHP with pdo_pgsql enabled in the PHPUnit process itself; `artisan test` may launch another PHP process without a per-command extension option. Configure DB_SSLMODE=disable only for the loopback local database. Never point RefreshDatabase tests at an institutional or usable local database.

The initial full-suite baseline was 173 passing and 36 failing tests. Compare final failures with that baseline rather than describing the full suite as passing.
