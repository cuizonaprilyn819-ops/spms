# Local SPMS application

Start the configured local application from PowerShell in the project folder:

```powershell
.\serve.ps1
```

Open `http://127.0.0.1:8891`. Keep the server process running while using SPMS.
Use `.\serve.ps1 -Port 8892` to select another port.

The launcher uses PHP on PATH, enables `pdo_pgsql` for that process when needed,
and reads the ignored `.env`. It uses the persistent local database rather than
the temporary in-memory test database.

## Persistent local PostgreSQL

This workstation stores PostgreSQL under `%LOCALAPPDATA%\SPMS`, outside OneDrive:

- `postgresql17\pgsql`: PostgreSQL runtime.
- `data-postgresql17`: persistent database cluster.
- `postgresql.log`: startup and server log.
- `secrets`: private local setup credentials.

The database listens on `127.0.0.1:55437` with password authentication. When `.env`
points to this runtime, the launcher starts it if needed. Other configured database
hosts are used as configured; the launcher does not install, reset, migrate or seed
them. Database credentials and application login credentials are separate.

The administrator is created during local provisioning. Its password is not stored
in the repository. No campus, office hierarchy or personnel duties are invented.
The [period setup](evaluation-periods.md) adds the eight periods for 2025-2028 with
July-December 2026 initially current. Commitment Planning still requires verified
organizational setup before activation.

Restart the web server after changing `.env`. Back up the database and `storage/app`
together. Do not delete the cluster folder or run `migrate:fresh` to restart the app;
those actions erase records.
