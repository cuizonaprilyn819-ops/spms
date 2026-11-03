# SPMS agent instructions

## Slash commands

- [docs/CODEX_COMMANDS.md](docs/CODEX_COMMANDS.md) is the canonical slash-command contract for this repository, adapted from the user's Doc-Ease commands.
- Execute recognized commands directly, parse arguments after the first colon, and support comma-separated targets where appropriate.
- `/help` lists the commands, aliases, purposes, and examples from that guide.
- Use the command's requested scope: `/find-bugs` reports findings; `/fix-bugs` implements fixes; `/commit` prepares a message; `/checkpoint` creates a commit.
- Default to `/serve_update: ON` at the beginning of every task, including new conversations. Explicit `OFF` or `AUTO` overrides this default for the current work.
- With `ON`, complete relevant checks, review the intended diff, commit only the intended files, and push to the current verified GitHub branch after each completed intentional repository change. Do not leave completed changes local without explaining a concrete blocker.
- GitHub publishing and live deployment are separate. SPMS currently has a verified GitHub remote; a live SPMS deployment path has not been established. Do not reuse Doc-Ease hosting, credentials, scripts, databases, or webhooks.
- Never force-push, merge, or publish to a different branch merely because automatic publishing is enabled. Preserve unrelated staged and unstaged changes.
- Keep credential values masked by default. Do not commit environment files, connector credentials, private keys, runtime data, or test/browser outputs.

## Repository and data boundaries

- This is a Laravel application. Use its session authentication, authorization, CSRF protection, request validation, Eloquent/query builder, and existing web/API response contract.
- Preserve historical reports, assignments, ratings, evidence, identifiers, and unconfigured lineage. Commitment planning is activated per evaluation period after explicit readiness verification.
- Separate planning approval from final performance review. Do not invent office relationships, duties, approval evidence, allocation inputs, or ratings.
- Use bound parameters for database values and allowlist any dynamic identifiers. Render user-controlled content with escaped Blade expressions; use Laravel Storage and path containment checks for file operations.
- Use disposable databases for migration and workflow testing. Never run destructive database preparation against existing institutional records.
- Run checks relevant to the change and report their actual scope. Existing failing tests do not become passing checks by being omitted; disclose known baseline failures when reporting suite health.

## Keep the root clean

- Keep framework entrypoints, repository metadata, build configuration, and stable project documentation in the root.
- Put reusable operational scripts in `scripts/`, temporary diagnostics in `tools/`, operator instructions in `docs/ops/`, and retained notes in `docs/notes/`.
- Put temporary runtime output in ignored `tmp/` or `output/playwright/`. Never place one-off debug endpoints or conversation dumps in the root.
- Before finishing, remove temporary helpers created for the task unless they are deliberately retained in the appropriate folder. Preserve existing user files.

## Visual consistency

- Use locally hosted Figtree for application interface typography.
- Preserve the original deep blue sidebar unless the user requests a different color. Use [docs/ui-palette.md](docs/ui-palette.md) for coordinated page colors, readable status, and restrained accents.
