# Client requirements acceptance checklist

Source: the client PDF titled “Design and Development of an Automated Strategic Performance Management System (SPMS) with AI-Assisted Formulations and Real-Time Tracking”. Reviewed 2026-09-21. This is an implementation assessment, not client sign-off.

Do not describe the whole system as compliant until every required main flow and alternative flow has been demonstrated with the client roles and actual deployment configuration.

| Use case | Assessment | Remaining acceptance work |
|---|---|---|
| 1 Login | Existing authentication and role-based dashboards | Account deactivation must prevent both login and continued use of an existing session. |
| 2 Logout | Existing logout, session invalidation and expired-form recovery | Verify idle expiration in two real browser sessions. |
| 3 Manage Users | Partial | Deactivation and preventing deletion of users with active work require implementation. |
| 4 Manage Employees | Partial | Preserve inactive employee records; verify each management role's permitted scope. |
| 5 Manage OPCR | Existing workflow | Demonstrate create, correction, approval and cascading prerequisites. |
| 6 Manage DPCR | Existing workflow | Verify approved OPCR prerequisite against the client's workflow. |
| 7 Manage UPCR | Existing workflow | Verify approved DPCR prerequisite. Supervisor is the confirmed Unit Head role mapping. |
| 8 Create IPCR | Existing workflow | Verify approved UPCR prerequisite and supervisor preparation for employees. |
| 9 Assign Targets | Implemented office/head/employee allocation path | Self-assignment and office allocation ceilings are tested; legacy assignments without allocations require explicit quantities. |
| 10 Submit Accomplishments | Implemented reported quantity and evidence path | Enforce active submission periods consistently. Employee-declared completion followed by later evaluation is the confirmed workflow. |
| 11 Upload Supporting Documents | Existing upload/download controls | Confirm accepted formats for each workflow. Office-target uploads currently support PDF/images; the separate MOV workflow also supports DOCX/XLSX. |
| 12 Generate Reports | Existing reports and exports | Exercise every role, report type and required filter; reconcile totals against source records. |
| 13 AI Generate Targets | Adapter prepared, NOT complete | Configure provider; add authorized UI context selection, editable saved drafts and evidence-backed acceptance tests. |
| 14 AI Summarize Accomplishments | Adapter prepared, NOT complete | Add UI, stored reviewed summaries and safe processing of supporting-document contents. Metadata alone is not document analysis. |
| 15 Evaluate Performance | Existing manual evaluation workflow | Validate calculation policy and preliminary ratings; AI recommendations are not integrated into evaluation yet. |
| 16 Send Notifications | Database notifications; optional queued email adapter prepared | Configure SMTP/queue/scheduler, exercise email failure fallback, add office-target deadline/risk reminders and action-based automatic clearing. SMS is explicitly future work in the PDF. |
| 17 AI Performance Recommendations | Adapter prepared, NOT complete | Authorized employee-level history, evaluator accept/edit/reject actions, persisted decisions and an AI audit trail remain required. |

## Monitoring implemented in this change

- Cumulative reported completed quantity is stored against a person's allocation when evidence is uploaded.
- Re-uploading evidence does not add the same quantity twice.
- Completed quantities cannot exceed the person's allocation.
- Dashboard office progress uses the office's allocated quantity, not the number of uploaded files.
- Unassigned quantities still count in the denominator; one completed share does not complete the entire office target.
- Legacy missing allocations are shown as unavailable rather than fabricated.
- Reported completion is explicitly distinguished from approved accomplishment.
- Dashboard data refreshes in the background every 15 seconds while visible, plus request time. It skips refresh while interacting with dashboard content, avoids overlapping requests, and retains last-loaded data with a warning if refresh fails.
- Monitoring rows are scoped to the signed-in employee, office head, assigning director or administrator.

## Required client acceptance demonstration

1. Director assigns two sessions to HRMO II.
2. HR Head assigns one session to themself and one to an employee.
3. Employee accepts and submits evidence with cumulative quantity 1.
4. The visible head/director dashboard shows 1/2 (50%) on the next successful refresh, without reloading the browser page.
5. Submitting additional evidence at cumulative quantity 1 leaves progress at 50%.
6. The second assignee submits quantity 1; the dashboard shows reported 2/2 (100%).
7. An unrelated employee/head cannot see these monitoring rows or open the evidence.
8. Disconnect the database: stale data remains visibly labelled; reconnect and the next refresh recovers.
9. Employee marks completion after submitting MOV. The evaluator rates the work afterward; recording completion does not require a separate head approval.

The automated tests support steps 1–7 at the application level. Multi-browser and deployed-service acceptance remain to be performed. No claim of instant push delivery or guaranteed update latency is made.

## Validation and confirmed client decisions

- Focused final run: 17 PHP tests, 116 assertions, all passed. Four background-refresh JavaScript tests passed.
- Live read-only smoke check: Lawly's dashboard rendered with the monitoring table and refresh marker; completed-value column exists.
- Broader suite run: 131 passed, 33 failed. This run preceded the additional email fallback test. Do not treat the suite as green.
- Failures include obsolete `Office Head` role references, missing legacy approval routes, role/report preparation expectations, attachment UI expectations and older assignment tests that omit required allocation values. Each must be reconciled against agreed requirements, not simply deleted or weakened.
- Confirmed by the client: use Supervisor for both Department Head and Unit Head workflows. Do not add separate roles or restore the obsolete Office Head role.
- Confirmed by the client: the employee marks completion with supporting MOV; the evaluator rates the work later. No head-approval gate is required for recording completion.
- AI and email were requested as integrations prepared for configuration. Live delivery and AI quality have not been verified. AI end-user workflows and audit persistence remain outstanding as listed above.
