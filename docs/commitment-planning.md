# Commitment planning and target cascading

Commitment Planning adds reviewed target lines to OPCR, DPCR, UPCR and IPCR reports. It is opt-in for a new evaluation period. Existing periods, approved reports, assignments, evidence and ratings retain their legacy workflow.

## Institutional basis and boundaries

The design follows the CSC requirement to align organizational and individual commitments, together with SLSU's published 2022 SPMS presentation (pages 11, 15–18 and 20–29). That presentation distinguishes Office, Division, Unit and Individual responsibilities; agreed commitments precede execution, and amendments require review. Its continued applicability must be checked with SLSU before activation. The software does not certify institutional compliance or replace signatures.

- [CSC SPMS framework](https://www.csc.gov.ph/programs/spms)
- [SLSU published SPMS 2022 material](https://southernleytestateu.edu.ph/images/2022/hrmd/Southern_Leyte_State_University_STRATEGIC_PERFORMANCE_MANAGEMENT_SYSTEM_SPMS_2022.pdf)

Allocation formulas below are application rules, not CSC rating formulas. Existing Q/E/T calculations are unchanged.

## Enable a period

1. Back up the database and private uploads. Review and apply the additive migration with `php artisan migrate`. Never use `migrate:fresh` on retained records.
2. Open **Commitment Planning → Reporting structure** as an administrator. Configure Office → Division → Unit relationships, mandates, heads and separate commitment reviewers. Heads must belong to their offices; reviewers use existing management roles. Imported identifiers are preserved. Reporting structures with plans cannot be reparented; use a new office record for a changed structure.
3. Record verified personnel duties. The office head or administrator can maintain them. Eligibility refers to actual duties and mandates, not merely the login role `Supervisor`.
4. Create/open a new evaluation period. In **Enable planning for a new period**, record the policy/readiness check and confirm activation. Periods already containing OPCRs or office assignments cannot be activated. Activation cannot be toggled back to bypass commitments.
5. Create the OPCR plan. Add target lines manually or have the **Planning Officer** (or Administrator) use **Target Planning Documents** to upload the top-management document and extract reviewed drafts into the OPCR. Document analysis does not directly assign work in enabled periods.
6. Submit commitments. The assigned reviewer records named external PMT and university approval, approval date and a private PDF/image evidence file. This is a record of an external approval, not a generated signature.

No live period is enabled by installing the code or migrating. Missing configuration is shown as an actionable validation error; the application never invents reporting lines, approvals or workloads.

## Cascade and review

- The parent head confirms which subordinate offices or personnel are eligible for each target, citing a mandate or duty. Lower plans expose only eligible source lines.
- **Get relevant targets** creates linked drafts with source/root references and the parent revision. Inherited standards, measurement meaning and deadlines remain traceable. Local titles and weights are reviewed separately; quantity shares are not rating weights.
- Ordinary employees obtain targets through their unit UPCR. A head's own IPCR may originate from their actual accountable level, with a recorded explanation and a trace to OPCR.
- Drafts may be prepared against pending parents, but confirmation requires the current parent commitments to be approved. Confirmed child shares are checked again under a root-plan database lock.
- Reviewers may endorse reconciled child drafts while a parent amendment is pending. An endorsement does not reserve a new share or permit execution. Parent approval publishes already-endorsed descendants together under the same root lock, after rechecking all quantities. This permits coordinated reductions without silently releasing previously confirmed shares.
- Each IPCR employee records agreement before commitment approval. Changing its draft target lines clears the agreement.
- Commitment approval leaves the performance report in draft. After execution, supporting outputs are validated, reviewer Q/E/T ratings are recorded, and the final performance report is submitted and approved separately.

An amendment preserves prior target rows, approvals and evidence. It opens a new revision and flags descendants for reconciliation. Previously confirmed shares remain reserved until replaced by a new approved revision. A different measurement/unit needs a separate target, rather than changing the meaning of an existing output lineage. Archived final reports cannot be amended through planning.

## Quantity rules

| Measure | Planning and aggregation |
| --- | --- |
| Count | Confirmed child shares cannot exceed the parent. Partial allocation remains visible in the parent denominator. Whole-number outputs use largest-remainder rounding, with stable office-ID tie-breaking. |
| Rate | Children inherit the percentage standard. Results use summed numerators divided by summed denominators; percentages are not divided among offices or averaged. No cases means unavailable. |
| Shared output | One accountable owner per branch at each level. Other participants use contribution-only target lines and reference the owner's output without adding quantity. |
| Manual | Preserve narrative standards and supporting evidence; do not compute a misleading numeric rollup. |

For count suggestions, the head records a common driver name/unit, recipient values, source/period, validity date and optional output capacity. Suggestions are proportional to these values, capped at capacity. Missing or stale recipient inputs require manual quantities. Unused capacity is shown as unallocated. A confirmed share above recorded current capacity requires revising the capacity first. The confirmed calculation inputs and override explanation are retained on the child target.

Reported output uses a unique reference within the OPCR target lineage. Incremental submissions represent new, non-overlapping outputs/batches. Cumulative submissions represent totals for the same assignment: only the latest total contributes, while the latest validated total is shown separately. Do not mix incremental and cumulative methods for one assignment or record the same real output under different references.

Duplicate references are rejected. Extra output is recorded without changing the commitment or assigning a higher rating automatically. Evidence validation and performance ratings are explicit human decisions. Shared contributions and summaries never add a second copy of an output.

Use **Add supporting file without changing quantity** to supplement an existing output. Additional attachments do not create another output or increase its reported quantity. Private evidence downloads require access to the corresponding plan or an approved shared-contribution assignment.

## Upward proposals

Personnel propose new targets or OPCR amendments through their supervisory chain. Office heads' proposals start with their parent office; employee proposals start with their own head. Reviewers forward, return or reject proposals with notes. Returned proposals can be revised and resubmitted. At the top office, an authorized reviewer adds an accepted proposal to an OPCR draft or reviewed amendment. Authors cannot review their own proposals; an administrator can handle a top-office proposal where an external review is needed. Downstream commitments still require the normal approval chain.

## AI and API behavior

AI uses the existing server-side `spms.ai` configuration. It receives the supplied mandate/duties and parent-target context and returns an advisory relevance explanation. The head still confirms eligibility; application arithmetic calculates shares. A provider outage creates no confirmed assignment and leaves manual entry available. No live AI request is required for acceptance tests.

`routes/spms.php` registers authenticated `/planning` screens and their matching `/api/planning` operations. Existing session cookies, CSRF protection, validation and rendered-HTML JSON responses are reused. Evidence and PDF/Excel downloads remain ordinary file responses. Planning exports, final report forms and dashboard commitment rows all call the same aggregation service. Rows at different report levels must not be added together.

## Verification and release checks

Focused checks:

```text
php artisan test --filter="CommitmentPlanningTest|PlanningUpgradeTest"
node --test tests/js/browser-api.test.js tests/js/dashboard-refresh.test.js
npm run build -- --configLoader runner
```

Run the build from the resolved physical project directory when using a Windows junction, so Vite manifest keys remain `resources/css/app.css` and `resources/js/app.js`.

The new tests cover the full four-level cascade, report/API reads, evidence, approval separation, rates, shared contributions, cumulative totals, overachievement, allocation limits, capacity/rounding, stale drivers, preserved reservations during amendments, heads' IPCRs, unauthorized branches, document import, upward proposals and additive migration preservation. PostgreSQL checks use a disposable local database; SQLite tests alone are not proof of PostgreSQL compatibility.

The original repository baseline has 180 tests, with 20 errors and 16 failures. These include obsolete `Office Head` expectations, missing legacy approval routes, report-preparation assumptions, attachment expectations and assignment tests without allocation values. Preserve and reconcile those tests against requirements; do not claim a green full suite while they remain.

Before production activation, verify the actual office hierarchy, personnel duties, external approval evidence, mail/queue configuration if enabled, and applicability of the published policy. Browser acceptance must use clearly identified synthetic records until real institutional acceptance is authorized.

### Implementation verification — 28 October 2026

- New workflow/upgrade checks: **24 passed, 156 assertions**, on both SQLite and disposable PostgreSQL **17.11**. The upgrade check preserved legacy offices, personnel and approved OPCR records across rollback/reapplication of the new migration.
- Full PHP suite: **204 tests, 168 passed, 36 existing errors/failures**. JUnit failure names exactly matched the isolated original-HEAD baseline (180 tests, 144 passed, the same 36 failures). No additional failing test was introduced.
- Browser navigation/dashboard JavaScript: **9 passed**. Production asset build succeeded; compiled Blade templates passed PHP parsing.
- Two concurrent PostgreSQL processes each requested a 60-output share of a 100-output target. One approval succeeded and the other was rejected; no 120-output allocation was created.
- Browser checks used synthetic director/division/reviewer/employee records on a disposable PostgreSQL database. They verified fetching a suggested 60-output DPCR share, separate reviewer approval, 40 remaining at OPCR, employee agreement and evidence submission. Supporting services prepared the intervening UPCR/IPCR fixtures; the full four-level behavior was separately tested automatically.
- The synthetic employee submission displayed reported 10 and validated 0; the OPCR export matched those amounts and the unallocated 40. Its one-page landscape PDF was rendered and visually inspected. No browser console errors were observed on the checked screen.
- Live AI responses, real external approvals, SMTP delivery and real-user institutional acceptance were not exercised. No existing or production period was activated.
