# MOV background processing

Original uploads and download paths are preserved. New uploads queue image previews
and, when AI is enabled, evidence analysis. One job covers a questionnaire batch.
Submission gates continue to require completed, sufficient AI findings for every target.
Processing and failed states are visible; owners receive automatic status updates.

## Files-first submission

New MOV uploads select a target and attach files before any questions are asked.
The upload queues one batch analysis and requires no user-written title or description.
AI supplies neutral wording and asks zero to eight questions only for report-critical
details missing or unclear in the files. Complete files proceed directly to a summary.
Existing questionnaire sessions remain usable during the transition.

Only the uploader can answer follow-up questions. Answers are validated against the
current analysis and target snapshot, saved across the batch, and trigger another
background analysis of the original files. They remain claims, separate from file
findings. Pending questions suppress the final statement and block report submission;
answers do not automatically satisfy the target or change reported progress or ratings.
Insufficient files require additional evidence, which can be uploaded as a new batch.
Restart the local launcher (or the supervised queue worker) after updating this code.

## Worker

`serve.ps1` starts a hidden MOV worker and stops that worker when the launcher exits.
Restart the launcher after pulling these changes. Use `-NoMovWorker` if a separately
managed worker already handles this queue.

On hosted installations, supervise this command independently of the web server:

```powershell
php -d max_execution_time=0 artisan queue:work mov-processing --queue=mov-processing --sleep=2 --tries=1 --timeout=600 --memory=384
```

The dedicated database connection has a 900-second retry interval, exceeding the
600-second worker timeout. The `jobs` and `failed_jobs` tables already exist; no new
migration is required. Queue storage is used even if the app's default queue is sync.
`SPMS_MOV_CONNECTION=sync` is for deterministic tests only; do not use it for the web app.
If a worker is stopped, files remain saved and submission stays blocked; processing
resumes with a worker. Interrupted/failed attempts can be retried by the uploader.

Enable PHP GD with WebP support in the worker. The local launcher probes and enables
its runtime's GD extension when available. Images over 16 million pixels retain
their original preview instead of risking excessive decoder memory. WebP previews
are bounded to 1600 pixels on the longest edge and stored privately. PDF, DOCX and
XLSX originals are not converted. Existing files use their original preview until
their next processing/retry job creates the smaller copy.
If re-encoding would increase file size, the original is served instead.

## OCR fallback

The first AI request always receives original images. Only an insufficient result
that explicitly reports unreadable/blurred text triggers local OCR. Ordinary partial
accomplishment does not trigger it. Configure a local Tesseract installation with
English language data using `SPMS_MOV_OCR_BINARY` (full executable path), or put
`tesseract` on the worker's PATH. No executable is installed automatically.

OCR uses the original image, fixed argument arrays, an eight-second per-file timeout
and bounded text. Transcripts are cached privately and treated as unverified text.
If useful text is extracted, one additional AI request receives both the original
images and transcription. Missing OCR or extraction failure preserves the initial
image-based finding. OCR does not replace visual evidence or establish completion.

## Timing and checks

Each analysis stores `findings.processing` with `queued_at`, `finished_at`,
`preview_ms`, `ocr_ms`, `ai_ms`, `total_ms`, and `ocr_used`. These distinguish queue
wait and preprocessing from AI request time. Compare equivalent target/file batches;
OCR can increase total processing time. Background processing improves response
latency, and previews reduce transferred bytes; neither guarantees faster inference.

Tests use disposable SQLite, fake storage, mocked OpenAI responses and fake OCR.
The asynchronous tests use the database queue and execute the saved job explicitly,
verifying that the HTTP upload did not call AI and that submission remains blocked.
No real MOV or institutional records are used for testing.

Verified on 2026-11-07: 333 PHP tests passed, 1 PostgreSQL-concurrency test skipped,
2,775 assertions; all 21 JavaScript tests passed; production build passed. The
Windows PowerShell launcher started a separate worker against disposable SQLite
with AI disabled, and both test processes were stopped afterward.

A synthetic 2400×1600 JPEG text page decreased from 671,328 to 119,516 preview bytes
(about 82%). The corresponding 70,873-byte PNG stayed in its original format because
WebP was larger. Original hashes were unchanged. Conversion took about 0.7–0.9 seconds
in the background for these samples. These numbers are fixture-specific, not a
measurement or guarantee of real OpenAI inference latency. No live AI call was made.
