# Test-suite reconciliation

The 17 baseline problems were reproduced on a disposable PostgreSQL database before editing. Runtime authorization, account roles, evaluation periods, historical records, and existing migrations are unchanged.

| Area | Baseline problems | Resolution |
| --- | ---: | --- |
| Personnel management | 3 | Use an explicitly active campus office for new assignments; assert Supervisor/HR Head/Planning Officer options and rejection of the retired Office Head role. |
| Application entry | 1 | Check the guest login redirect and authenticated dashboard redirect, rather than expecting a public home page. |
| HRM account creation | 1 | Use a valid campus recipient and the current Supervisor role option. |
| IPCR management | 3 | Test owner-scoped creation/viewing and duplicates against an approved, same-office UPCR. A forged employee ID cannot transfer ownership. |
| Office targets | 2 | Exercise Supervisor scoping and edit restrictions instead of creating the retired Office Head role. |
| OPCR management | 3 | Exercise Campus Director creation for the assigned office, uniqueness, and Supervisor read scope. |
| Report attachments | 1 | Restore the missing IPCR attachment panel, using the existing component and its same-office Supervisor authorization. Existing upload/download and cross-office checks remain. |
| Preparation permissions | 1 | Cover all current roles and all four forms, including heads' personal IPCRs and the office-assignment prerequisite. |
| Strategy | 1 | Exercise the Planning Officer document-entry workflow and active-office allocation requirements; preserve multi-objective/multi-target and ownership checks. |
| Supervisor assignments | 1 | Keep supervisor delegation/editing and outsider restrictions; Employee access remains personal IPCR access, without organisational UPCR administration. |

`OfficeFactory::activeForCampus()` is an explicit test state. Generic imported/unconfigured office fixtures are unchanged, and no production office classification or hierarchy is fabricated.

Additional checks reject unapproved and other-office IPCR parents, preserve an existing report on duplicate submission, reject cross-office OPCR creation, and reject obsolete role escalation. No test is deleted or skipped to obtain a passing suite.

## Validation

- Full PostgreSQL suite: **266 tests, 1,936 assertions**, zero failures, errors, or skips. Poppler is configured for the real PDF import integration test.
- JavaScript: **15 tests passed**. Asset build and Blade compilation pass.
- Browser: an authorized Supervisor sees the restored IPCR panel, uploads a synthetic PDF through the session/CSRF workflow, and downloads it successfully. The Employee owner still has no Supervisor upload controls. The mobile page has no horizontal overflow.
- Preservation: a private backup and before/after fingerprints verify unchanged local account rows and roles, PCR/report relationships, periods, and original PDF/page-image files. Temporary browser fixtures use a disposable database and are removed after verification.

This resolves the recorded suite baseline; it is not a claim that every possible production scenario has been tested.
