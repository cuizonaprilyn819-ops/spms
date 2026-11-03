# Local/adviser integration checkpoint

Integration branch: `codex/integrate-adviser-2026-11-03`.

Parents: local checkpoint `03f82b0` and adviser branch
`origin/codex/commitment-planning-ui` at `df951c1`.

## Resolved behavior

- Keep the confirmed role policy: Campus Director prepares OPCR and personal IPCR;
  supervisors prepare DPCR/UPCR and personal IPCR; employees prepare IPCR.
  Preserve the system-wide Planning Officer, HR and Campus Director singleton seats.
- Combine parent-office submission notifications with planning visibility checks.
- Preserve Word downloads alongside historical source/page/export downloads.
- Preserve assigned weight locks and submitted scope values in planning forms.
- Include strategic program and program outcome in the adviser's wording previews.
- Keep MOV questionnaires, batch analysis and evidence sufficiency gates alongside
  target conflict checks. Batch statements also respect the configured report template.
- Show a director's own cross-campus assignments alongside managed-office progress.
- Use ZipStream 3.1.2 so the incoming spreadsheet dependency supports PHP 8.2.

## Runtime and database preparation

The existing institutional database was not migrated or reset during integration.
New historical archive, multi-campus planning and target-assistance migrations still
need the normal reviewed migration process before using this branch with that database.
Existing periods remain in their legacy workflow; multi-campus planning requires
explicit readiness/configuration and activation.

Install dependencies from `composer.lock`. Historical Word/Excel exports require
PHP GD and ZIP; these DLLs exist locally but are disabled in the default XAMPP INI.
The integration checks enable them per command with `-d extension=gd -d extension=zip`.
Enable both extensions in the PHP configuration used by the application before using
historical exports. Poppler paths are needed for historical PDF import.

## Validation

Run PHP tests against the disposable SQLite database configured in `phpunit.xml`:

```powershell
& "C:\xampp\php\php.exe" -d extension=gd -d extension=zip -d max_execution_time=0 vendor/phpunit/phpunit/phpunit
node --test tests/js/*.test.js tests/js/*.test.mjs
npm run build
```

The suite forbids unfaked HTTP requests. Real OpenAI requests and live institution
records are not used. PostgreSQL cross-process concurrency and Poppler-dependent
import checks require their respective disposable/runtime environments.

Verified results: 330 PHP tests (328 passed, 2 environment-dependent skips),
2,695 assertions; 17 JavaScript tests passed; production Vite build passed.
Composer validation and platform checks passed with GD and ZIP loaded.

The separate local checkpoint and private backup remain available. Research images,
scripts and generated output outside the application changes are not part of this merge.
