# Fresh workflow responses and private MOV files

Laravel responses use `private, no-store, max-age=0, must-revalidate`, including
API-rendered pages, permission failures, downloads and previews. Authentication and
authorization still execute on each request. Browser API requests use
`cache: 'no-store'`, and a restored browser-history snapshot reloads the current page.
Public versioned CSS/JS, fonts and logos bypass Laravel and retain static caching.

Legacy report MOVs remain on their original storage disk to preserve records and
paths. Their `/storage/movs/...` URLs are blocked by the local `serve.ps1` router
and Apache rewrite rules; the authenticated Laravel download/preview routes remain
available to authorized users. Restart `serve.ps1` after updating its router.
For another web server, deny direct access to `/storage/movs/` and serve MOVs only
through the application routes. Do not add a CDN/public-cache rule for these files.

Reference-selector file caching is unchanged. It does not supply permission decisions,
approval state, notifications, ratings, or MOV processing status. This change does
not automatically move legacy files or alter report history.
