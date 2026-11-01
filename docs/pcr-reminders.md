# PCR reminders and submission timeliness

Planning Officers and administrators configure PCR creation closing dates, submission deadlines, and the reminder window in Evaluation Periods. These dates are separate from the January-June / July-December target duration, and from institutional approval deadlines. Dates include the end of the selected day in the application timezone, currently UTC.

## Behaviour

- An office with assigned targets but no matching UPCR receives a daily head reminder until the UPCR is created or the evaluation cycle is closed. After creation closes, that reminder directs the head to the Planning Officer for an authorized completion/extension. The system does not fabricate a UPCR or approval.
- In the configured window before creation closes, personnel without required PCRs receive daily reminders. Heads receive the relevant organizational PCR reminder as well as their own IPCR reminder. Users without an active campus office are excluded.
- Near the submission deadline, created but unsubmitted PCRs receive submission reminders. Completed tasks stop generating reminders. Closed evaluation cycles do not generate reminders.
- Successful deliveries are deduplicated per person, report type, office, period, date and channel. Failed email deliveries can be retried without repeating a successful in-app delivery.
- Log/array mailers do not send email and are not counted as actual email delivery. In-app reminders remain available.
- Once creation closes, new PCR creation is rejected. Existing drafts and corrections may still be submitted, with lateness recorded. Planning Officers can explicitly adjust the creation closing date when an approved exception is needed.
- First submission and the applicable deadline are recorded atomically for OPCR, DPCR, UPCR and IPCR. Commitment-planning submissions are identified as such. Returns, resubmissions and later deadline changes do not overwrite that original record.
- The IPCR reviewer sees submission evidence and rates Timeliness using approved work-plan standards. Where a submission deadline is configured, IPCR approval records Q/E/T and their average. There is no invented fixed lateness penalty. Historical submissions without first-submission evidence are labelled as legacy/unverified.
- Institutional approval cutoffs, automatic target validity after 15 working days, and formal target modification approvals are separate rules. This feature does not silently auto-approve reports, reinterpret approval cutoffs as submission deadlines, or overwrite approved targets.

## Local setup (Windows / XAMPP)

Run migrations and clear cached configuration from the project directory:

```powershell
& "C:\xampp\php\php.exe" artisan migrate
& "C:\xampp\php\php.exe" artisan config:clear
```

Configure the dates in Evaluation Periods. Preview pending tasks without sending notifications:

```powershell
& "C:\xampp\php\php.exe" artisan spms:send-pcr-reminders --dry-run
```

Keep the application scheduler running in a separate terminal:

```powershell
& "C:\xampp\php\php.exe" artisan schedule:work
```

The reminder command is scheduled daily at `SPMS_PCR_REMINDER_TIME` (default `08:00`, application timezone). A continuously running server scheduler is required for unattended delivery. On a deployed server, configure the standard Laravel `schedule:run` cron/task rather than relying on a developer terminal.

To deliver the current pending notifications immediately, an administrator may run `artisan spms:send-pcr-reminders`. This sends reminders to eligible users; it is not a preview.

## Email setup

The current local mailer is `log`, so real email delivery is not active. Obtain an authorized SMTP/provider account before enabling it. Configure its actual host, port, authentication, sender and any required TLS settings privately in `.env`, set `MAIL_MAILER` to the supported real mailer, and keep `SPMS_PCR_EMAIL_REMINDERS=true`. Use a publicly reachable `APP_URL` for email action links on a deployed system. Clear the configuration cache after changing settings. Do not commit SMTP passwords or copy them into chat.

No outgoing email has been sent as part of development verification; delivery tests use Laravel notification fakes.
