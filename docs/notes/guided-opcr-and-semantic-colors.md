# Guided OPCR and semantic colors — 1 November 2026

## Delivered behavior

- Shared Sneat component palette across messages, statuses, tabs, dropdowns, summary accents and progress, with Figtree and the deep blue SLSU sidebar retained. Primary form actions now receive the violet action tone after icon enhancement.
- Private, resumable root-OPCR scope drafts, explicit responsible departments, recorded duty explanations, combined/per-department quantity interpretation, deterministic confirmation and optional AI advice. Extracted targets use the same guided review page.
- Configurable campus catalogue, explicit office classifications, central/campus Planning Officer grants, readiness-controlled period activation and University OPCR → Campus OPCR → DPCR → UPCR → IPCR cascading.
- Scope-aware eligibility, per-department branch ceilings, inherited standards, retained approval evidence, reviewed upward proposals, immutable approved revisions and reconciliation. Operational projections, exports and reporting paths use the same target relationships.
- Personnel editing accepts the configured campus offices shown in its selector while preserving retained inactive assignments and password hashes. The PostgreSQL rollback guard for the older IPCR uniqueness migration now selects its grouped columns explicitly.

## Verification

The initial disposable PostgreSQL baseline passed **266 tests / 1,936 assertions**. The completed suite passed **284 tests / 2,076 assertions**, with no failures or skips. The added cases cover guided/private/idempotent confirmation, five-level reconciliation, recipient exclusion, quantity ceilings, explicit grants, file/export boundaries, upward proposals, head IPCRs, document sources, amendments and activation. A separate two-process PostgreSQL test confirms one draft concurrently and produces exactly one target.

All **15 JavaScript tests** passed. Production assets built successfully; Blade compilation, Pint and whitespace checks passed. Shared component foreground/background pairs have a minimum normal-text contrast of **4.84:1**.

Browser checks used an isolated PostgreSQL copy, with AI and outgoing mail disabled. Sixteen main page families passed at 1440 and 390 pixels; operational report lists and selected create/edit/detail pages passed at 320 and 768 pixels without document-wide horizontal overflow. Guided entry, a calculated total of four from two per department, confirmation, submission, synthetic external approval, alert dismissal, hover/focus labels and Escape were exercised. Synthetic database records stayed in the disposable copy. The final file audit found one synthetic approval PDF in local private storage; it was identified by its exact known content hash and removed. All original source hashes remained unchanged. AI availability failure and manual continuation were tested; no live AI response is claimed.

The usable installation received only the additive migration. Before/after comparisons preserved **nine accounts**, account IDs/password hashes/role assignments, **three historical PCRs** and their participant relationships, **eight periods** and **89 source/evidence file hashes**. The browser confirmed **July–December 2026** remains current and contains no preview records. Multi-campus activation remains disabled pending actual organizational configuration and readiness verification.

No private sources, credentials, database backups, rendered exports, browser output or unrelated endorsement document belong in the release. See `docs/ops/multi-campus-planning.md` for setup and operating instructions.
