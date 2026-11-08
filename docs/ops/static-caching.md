# Browser caching for static assets

Restart `serve.ps1` to load the local static-file router. Built CSS and JavaScript
whose filenames contain Vite hashes are cached for one year with `immutable`.
Rebuilding changes their URLs, so browsers fetch the new version. The two Figtree
fonts, SLSU logo and favicon are cached for one day; these stable filenames are
not immutable. Local requests support ETag/Last-Modified revalidation and HEAD.

Only these public reference assets receive the new policy. HTML, API responses,
the build manifest, public-storage uploads and authenticated MOV downloads retain
their existing headers. No service worker, Redis or database cache is introduced.

Apache deployments rooted at `public/` use the matching rules in `public/.htaccess`
when `mod_headers` and `mod_setenvif` are enabled. PHP's `artisan serve` does not use
the custom router: use `serve.ps1` locally. Other web servers need matching static
location rules; do not apply public caching to all extensions or all responses.

To check, open browser Developer Tools > Network. Reload normally with **Disable
cache** unchecked. Inspect `Cache-Control` on an app CSS/JS file, `figtree.ttf`, and
`logoslsu.png`. Subsequent normal navigation should use memory/disk cache. A hard
reload may intentionally bypass the cache. Existing static assets may require one
reload to receive the new headers. Static files at unchanged URLs can remain cached
for a day after replacement; use a new filename or URL for an immediate update.
