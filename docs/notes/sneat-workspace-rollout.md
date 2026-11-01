# Shared workspace refresh

The approved Sneat-inspired design is implemented through the shared Laravel layout, page styles, components, and progressive enhancements. SLSU branding and locally hosted Figtree are retained. Button tones are documented in `docs/ui-palette.md`.

## Behaviour

- Grouped navigation follows existing role boundaries. Historical PCRs and strategy have direct navigation entries.
- The current-period indicator is read-only and shows the latest open period; it does not claim to filter unrelated pages.
- Reports, historical records, and profile settings have keyboard-accessible tabs. Without JavaScript, all sections and normal anchors remain available.
- Simple tables become labeled records on small screens. Institutional matrices retain their column structure and horizontal scrolling.
- Optional table search explicitly filters the loaded page. Existing server pagination and personnel directory filters remain authoritative.
- PCR lineage displays actual recorded parents only. It does not reconstruct historical or missing relationships.
- Actions retain their Laravel forms, CSRF protection, authorization, and rendered-page API responses. No demonstration content from the design preview is installed in the usable database.

Personnel editing replaces the unsupported `orWhereKey` query with a bound `orWhere`. The existing inactive/current office remains available; unrelated inactive and other-campus offices remain unavailable for new assignments. Saving does not reset credentials.

## Verification

- Production asset build, Blade compilation, PHP syntax checks, and 15 JavaScript tests pass.
- A focused PostgreSQL run passes 79 tests with 497 assertions, including real PDF import with configured Poppler, archive ownership, revision protections, exports, planning, authentication/recovery, API responses, and the three new personnel-office regression tests.
- The full PostgreSQL suite runs 264 tests: 17 unsuccessful tests are identical to the refreshed baseline (7 errors, 10 failures). One PDF-import test skips when Poppler paths are absent; the focused run supplies them and passes that test. The complete suite is not claimed to pass.
- Browser review covers desktop and mobile page families, role-specific creation/editing, historical owner restrictions, source-page switching, keyboard tabs, action labels, reduced motion, and form submissions.
- Browser planning checks use a disposable database: a linked 60-of-100 commitment is submitted and independently approved, leaving 40 unallocated while the performance report remains separate.
- No schema migration, role reassignment, period activation, external AI/email activation, or institutional data change is required by this release.

Existing failures concern legacy role/fixture expectations, report preparation/attachments, IPCR management, and strategy/supervisor assignment. Their complete test identities are captured in the local comparison logs, rather than being removed or rewritten as part of this visual change.
