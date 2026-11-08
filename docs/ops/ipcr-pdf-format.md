# IPCR school PDF layout

IPCR PDF downloads (preview exports and approved-report exports) use a dedicated DomPDF Blade template on legal landscape paper. Other report types retain their existing PDF layout; Word and Excel exports are unchanged.

The template contains the commitment statement, employee signature, reviewer/approver fields, rating scale, grouped targets, Q/E/T/A columns, rating summary, development comments, assessment signatures and a separate MOV inventory. No blank separator page or report-list cover page is inserted.

- Employee identity and position come from the report employee, including when another person prepared it.
- Reviewer identity uses the office's configured planning reviewer, falling back to the Planning Officer account. Names identify designated people; blank signature dates do not imply completed review.
- Approver identity uses the recorded approver, falling back to the parent office's designated head. Approval date uses the report's saved approval timestamp only.
- Section labels use saved target category/weight-budget metadata. Unclassified targets remain visibly unclassified. Section percentages sum recorded target weights; missing weights are not estimated.
- Budget, aggregate totals and adjectival rating remain blank when the report has no corresponding saved value. Final average uses the saved report average; this export does not calculate a new rating.
- The MOV page lists recorded employee-owned evidence filenames and descriptions. It does not merge original PDF attachments or embed their pages. Access to originals continues through the authenticated MOV workflow.

Verify with the focused `IpcrSchoolPdfTest`, existing `ReportGenerationTest` and `ReportTargetWordingTest` against the disposable test database. Optional `SPMS_PDF_QA_OUTPUT` saves the synthetic test PDF to a chosen local QA path for rendering and visual inspection.
