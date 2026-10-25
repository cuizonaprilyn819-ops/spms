# Optional AI and email configuration

These adapters are disabled by default and require deployment configuration.

## AI

Set `SPMS_AI_ENABLED=true`, `OPENAI_API_KEY`, and `OPENAI_MODEL` in the deployment environment after approving the model and data-sharing policy. Never put the key in browser code or commit it.

`App\Services\AiAssistant::draft()` supports `targets`, `summary`, and `recommendations`, returning advisory plain-text drafts. It uses the Responses API, disables provider response storage, bounds output size and request time, and returns a safe manual-entry fallback message on failure. A caller must authorize and minimize the supplied context. It does not inspect uploaded document contents automatically, assign ratings or save approvals.

Reference: https://developers.openai.com/api/docs/quickstart

The authenticated layout includes a bottom-right chat assistant at `POST /assistant/chat`, limited to ten requests per minute per user. It sends only the entered message and up to eight recent conversation messages to OpenAI. Responses render as text, not HTML. Chat is held in page memory and clears on navigation; it cannot read records, inspect files, or perform assignments. The `chat` task supports SPMS guidance and rewriting supplied text. The API key belongs in the server `.env`; `API key.txt` is excluded from Git and is not read at runtime.

## Main Campus document workflow

Campus directors open **Main Campus Documents** (or Targets → Upload Main Campus Document), choose an open evaluation period, and upload PDF, Word or Excel files up to 20 MB. Files are stored privately; only the uploader can view or download the original. Analysis sends the file and active office catalogue to OpenAI, not employee records.

Choose Analyze, then review each extracted target and its source passage. CD-only targets remain visible but cannot be assigned to offices. For other targets, correct the measurable details, select/add offices, and enter allocations whose sum equals the total. Confirm to create the target and send office-specific AI briefings to configured office heads. Supervisors retain the final decision to handle work themselves or delegate using the existing assignment controls. AI recommendations are advisory, not assignments.

Analysis is limited to 40 targets per document; split larger documents. Spreadsheet processing may include only the first 1,000 rows per sheet. Check every extraction against the source. Failed analysis can be retried; interrupted processing becomes retryable after five minutes. Provider failures during assignment create no target. Repeated successful submissions do not duplicate targets. Editing an assigned target clears its now-stale AI briefings.

## Email

Configure Laravel's `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` and transport security settings for the approved provider. Set `APP_URL` to the actual reachable application URL.

Use `QUEUE_CONNECTION=database` and run `php artisan queue:work --tries=3`. Then enable `SPMS_EMAIL_NOTIFICATIONS=true`. Keep this flag false until SMTP and the worker are configured; a synchronous queue could delay normal page requests.

New database notifications enqueue an email copy after transaction commit. Failed email jobs retry, and the original dashboard notification remains available. Already-read notifications are skipped. Repeated worker delivery can resend an email; this is not an exactly-once delivery system.

Do not enable email just to test a production recipient without authorization. Use an approved test mailbox or the log mailer first. Validate queue retries, dashboard fallback, and the deployed scheduler separately.

## Database changes

Apply pending migrations in order, including target acceptance and per-person allocations, before using the completion-quantity migration:

`php artisan migrate --force`

Review pending migration contents before deploying. Do not substitute `migrate:fresh`, which deletes existing tables.
