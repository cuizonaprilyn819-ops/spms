# Evaluation period setup

The initial catalogue contains eight calendar half-year periods:

| Period | Initial status |
| --- | --- |
| January-June 2025 | Closed |
| July-December 2025 | Closed |
| January-June 2026 | Closed |
| July-December 2026 | Current / Open |
| January-June 2027 | Closed |
| July-December 2027 | Closed |
| January-June 2028 | Closed |
| July-December 2028 | Closed |

The interface calls these periods `1st Quarter (January-June)` and
`2nd Quarter (July-December)`, using the user's requested terminology and date ranges.
The existing database values `1st Semester` and `2nd Semester` are retained for
compatibility with historical records; labels are translated when displayed.

After applying migrations to the configured database, run:

```powershell
php artisan spms:prepare-periods --set-current
```

This adds missing periods and explicitly opens July-December 2026, closing any other
open period. Existing IDs, reports, attachments, planning configuration and other
periods are retained. No reports, users, duties or approval evidence are created.
Opening a period does not enable Commitment Planning; readiness verification remains
a separate administrator action.

For subsequent runs, omit `--set-current` to add missing periods without changing
the administrator's current selection or reopening deliberately closed periods:

```powershell
php artisan spms:prepare-periods
```

The regular `php artisan db:seed` and `composer setup` also include this catalogue.
They select the initial default only when the period table is empty. New OPCR and
target-document forms preselect the open period. Values returned after validation
retain the user's selection. Creating an office target already leads to the
target-document screen; existing target editing behavior is retained.

## Recommended Period Reference behavior (not yet implemented)

- A reference-period selector should filter the user's permitted records without
  changing the institution's current open period or granting editing access.
- Historical periods should be read-only. Only designated period administrators
  should open or close periods.
- Employees should edit their own draft or returned records only during an
  authorized editing window. Submitted and approved records should remain locked.
- Historical corrections should use a reviewer-authorized, time-limited window for
  a specific report, with a reason and preserved revision history.
- Future periods should be identified as upcoming. The current implementation has
  only Open and Closed states, so future periods remain Closed until opened.

Closed-period restrictions are enforced by Commitment Planning but are not
consistent across legacy report and accomplishment edits. A reference selector
alone would not fix those authorization gaps; verify all write paths before
presenting historical periods as fully locked.
