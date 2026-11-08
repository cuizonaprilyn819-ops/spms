# Office head account roles

- **Division Head** prepares DPCR and personal IPCR.
- **Unit Head** prepares UPCR and personal IPCR.
- Existing **Supervisor** accounts retain their previous office-based behavior.

Run `php artisan migrate` with the application's configured PHP runtime to add the two role definitions. The migration is idempotent and does not change any account assignments. Its rollback retains role definitions to protect assigned users.

In personnel management, edit each confirmed head and select the appropriate role. Verify the person's responsible office before saving. Do not infer head assignments from office names. Role selection does not configure the office's planning level, verified mandate, parent office, designated head or planning reviewer; configure those separately before activating commitment planning.

Both explicit roles retain existing scoped head functions for targets, employees, evidence, reports and notifications. Division Head cannot create a legacy UPCR; Unit Head cannot create a legacy DPCR. Personal IPCR remains available to both. Existing planning approval rules and report history are preserved.
