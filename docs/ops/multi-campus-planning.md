# University and campus target planning

The existing local periods retain their workflow. Multi-campus planning is off until an administrator explicitly activates an empty, open period. Historical PCRs are never moved into operational commitments.

## Prepare the organization

1. Open **Commitment Planning → Campuses and planning access** as an administrator.
2. Add the actual campuses. Imported campus references are optional mappings, not authority grants.
3. Create or classify a university root office, campus offices, departments/divisions and units. Existing identifiers and imported campus values remain unchanged. A branch with commitment plans cannot be reclassified or reparented.
4. Assign personnel using the existing personnel pages. In Commitment Planning, record duties and verify each office’s mandate, head, separate reviewer and reporting relationship. Use university office → campus office → division → unit. No account’s login role alone establishes its organizational level.
5. Assign the existing Planning Officer role using normal personnel administration, then record explicit central and campus grants. Scope grants do not add personnel-management privileges. Administrators retain management access.
6. Record policy, hierarchy, reviewer and allocation readiness and activate the chosen empty period. Existing accounts, periods, reports, AI settings, email and reminders are unchanged.

## Set and cascade a target

Create the university OPCR first. Open **Add an OPCR target → Set target and responsibility**. The same guided page opens from an extracted document’s review page.

Enter the target and source, choose coverage and participating campuses, select responsible departments, record why their verified duties match, and specify combined total or per-department quantity. Coverage never automatically selects recipients. Per-department mode applies to divisible counts only.

For two selected departments, entering 2 as a combined total commits 2; entering 2 per department commits 4. The server calculates and displays the total before confirmation. A private saved draft can be resumed by its creator. AI reviews the supplied target and verified mandates only when requested and configured; manual confirmation remains available when AI is unavailable.

Confirmation creates a linked target in the OPCR draft and records immediate branch eligibility. It does not approve commitments or reserve quantities. Campus OPCRs fetch relevant university targets; DPCRs fetch campus targets, then UPCRs and IPCRs follow their verified duties. Units and people still need confirmed eligibility. Each stage preserves source standards and lineage.

Combined totals use manually confirmed shares or recorded workload/capacity drivers. Missing or stale drivers do not create an equal split. Per-department mode additionally limits each department and its campus branch to the recorded quantity basis. Parent locking and idempotent confirmation protect concurrent requests.

An upward proposal follows the actual reporting line. At the university root, materialization opens guided scope review; it remains pending until confirmation. Approved targets require an amendment, retain their prior revision, and flag affected descendants for reconciliation. Campus OPCR endorsements retain their external approval evidence while awaiting university OPCR approval.

## Access and records

Campus grants cover assigned campuses; central grants cover university planning. Preparation, named reviewer and head responsibilities remain explicit. Pages, rendered API responses, operational projections and exports use the same planning boundary. Private scope drafts belong to their creator. Historical archive access remains attached to the original owner and named participants.

The Office Targets page is a projection of confirmed allocations, not a second quantity ledger. Reported output, validated output, unallocated responsibility and ratings remain separate. Progress rows at different levels must not be added together.

Before a workstation upgrade, back up PostgreSQL and private storage, verify fresh installation and a restored-copy upgrade, and compare account identities, historical relationships, periods and source hashes. Never use a usable institutional database for destructive tests.
