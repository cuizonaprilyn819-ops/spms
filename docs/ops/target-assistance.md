# AI wording and conflict review

Editable target and report forms offer **Rephrase with AI**. Compare the original
and suggested wording, inspect protected details, then choose **Use suggestion**.
This changes the form only. Save normally. Changing the text or structured inputs
after applying a preview treats it as a manual edit; stale saved previews are rejected.
Historical source transcriptions cannot be paraphrased. Their correction requests
and administrative notes can use the wording helper.

Open **Target conflicts** in the sidebar or **Check target conflicts** on a record.
Draft scans are private. Submission/resubmission and legacy assignment confirmation
publish checks and notify responsible preparers and configured reviewers in the app.
AI suspicions are labeled as unconfirmed. Similar wording in a linked cascade is
expected. A completed check is not a guarantee that every conflict has been found.

Independent assigned reviewers record a correction request, an addressed response,
or dismissal with a reason. Verified numerical and authorization violations cannot
be dismissed. New source changes make earlier results outdated. Approved records
are preserved; corrections still use existing amendments and return workflows.
If AI fails, record a manual conflict review and address any rule findings before
approval. No email delivery is enabled by this feature.

## Background processing

Apply additive migrations normally after backing up the database and private storage.
The feature uses existing AI configuration and can be disabled with
`SPMS_TARGET_ASSISTANCE_ENABLED=false` during maintenance. Do not disable it merely
to bypass an unresolved review.

Run a dedicated worker alongside the web application:

```powershell
php artisan queue:work target-assistance --queue=target-assistance --sleep=2 --tries=2 --timeout=240
```

On this local workstation, add `-d extension=pdo_pgsql` before `artisan` if PHP's
PostgreSQL extension is not enabled globally. The dedicated connection has a
300-second retry interval. It does not process existing mail/reminder queues.
Use **Retry conflict check** for failed/outdated checks. Restart this worker after
deploying code. Monitor failed jobs and the application log; do not log API keys or
export assistance snapshots to Git.

## Validation boundaries

The focused assistance suite explicitly enables the new review gate. Legacy tests
retain their original scope with the feature disabled through phpunit.xml. Run both
the full suite and focused assistance tests against disposable PostgreSQL, not an
institutional database. Browser QA must use isolated data and label stubbed AI
results separately from actual provider availability.
