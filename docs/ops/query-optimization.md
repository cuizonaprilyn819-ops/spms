# Query optimization

The office-target MOV section loads ten uploads per page, with employee and analysis
relations eagerly loaded. Its independent `target_mov_page` query parameter and
`#target-movs` fragment preserve navigation. Existing owner/managed-office visibility
rules and batch analysis are unchanged. Older uploads are retained on later pages.

Managed-office traversal now reads only campus office IDs and parent IDs once,
instead of querying for every hierarchy depth. Planning access reads only the fields
needed to evaluate access; administrators fetch IDs directly. No authorization result
is cached between requests.

The additive `2026_11_08_160000_add_mov_and_notification_lookup_indexes` migration
adds composite indexes for target MOV owner/office timelines, notification timelines
and unread counts. Existing position-title lookup and morph indexes are retained.
Apply with `php artisan migrate` on the intended database after reviewing deployment
timing; building indexes on populated PostgreSQL tables can briefly block writes.
No institutional database migration is run by the local acceptance tests.

Disposable SQLite tests check pagination, isolation, bounded analysis queries, one
hierarchy query across multiple levels, index creation/rollback and index selection
with EXPLAIN QUERY PLAN. These checks do not establish PostgreSQL execution timings
or quantify production speedups. No live AI request is needed.
