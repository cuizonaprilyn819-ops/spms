# PostgreSQL setup and cutover

PostgreSQL is the default for new installations. Existing installations with
`DB_CONNECTION=mysql` in `.env` continue using MySQL until cutover. Enable
`pdo_pgsql` in both the CLI and web server PHP runtimes.

## Hosted connection

Create a hosted PostgreSQL database and use its connection details and CA certificate.
Keep credentials in `.env`, outside source control. MySQL service credentials
cannot be reused for a PostgreSQL service.

```dotenv
DB_CONNECTION=pgsql
DB_HOST=your-postgresql-host
DB_PORT=5432
DB_DATABASE=spms
DB_USERNAME=your-postgresql-user
DB_PASSWORD=your-postgresql-password
DB_SSLMODE=verify-full
DB_SSLROOTCERT="C:/path/to/postgresql-ca.pem"
```

Use the provider's actual port. Remove or update any existing `DB_URL`, which can
override individual settings. Use the CA issued for PostgreSQL. For local
PostgreSQL without TLS, explicitly set `DB_SSLMODE=disable`.

## Preserve existing records

Keep the working connection until a transfer has been validated. Changing the
driver or running migrations does not copy records.

1. Back up MySQL and uploaded files and verify that the backups can be restored.
2. In a separate migration environment, configure an empty PostgreSQL destination
   and run `php artisan config:clear` followed by `php artisan migrate`. Use the
   application migrations as the destination schema. Do not seed demo records.
3. Transfer application data in foreign-key dependency order, preserving IDs,
   password hashes, timestamps, JSON values, and attachment paths. Convert MySQL
   boolean values to PostgreSQL booleans. Retain the destination's `migrations`
   table rather than importing the source migration history.
4. Reset PostgreSQL ID sequences after importing explicit IDs. Compare table counts,
   relationships, and representative records. Verify login, permissions, search,
   dashboards, uploads, reports, and new inserts. PostgreSQL `LIKE` is case-sensitive;
   review search behavior during acceptance.
5. During final cutover, pause application writes and workers, transfer final changes,
   repeat validation, and update the live `.env`. Run `php artisan config:clear`,
   restart application processes and workers, and verify `php artisan migrate:status`
   before reopening writes.
6. Retain the MySQL backup and original connection securely for rollback. After
   PostgreSQL accepts writes, rollback requires reconciling those new records.

Never use `migrate:fresh` on a database whose records must be retained. The transfer
must be executed separately; these configuration changes do not perform it.

## Testing and AI

`php artisan test` uses isolated SQLite through `phpunit.xml`. Passing those tests
does not establish PostgreSQL compatibility. Validate migrations and workflows
against a disposable PostgreSQL database before cutover.

Add pgvector when document retrieval is implemented, after confirming hosting
support and choosing embedding dimensions. It is not required for the current schema.
