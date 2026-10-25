# SPMS browser API

SPMS now provides a first-party API at `/api`. The signed-in interface uses
`fetch` for internal navigation, filtering, pagination and form submissions.
The initial page load and JavaScript-disabled fallback still use Blade web
routes. Login, logout, password management and file downloads use normal browser
requests.

Flow: browser → `/api` → existing controller validation/authorization → Eloquent → database.

## Routes and authentication

`routes/spms.php` defines shared workflows. `routes/web.php` registers them both
at their original URLs and under `/api`, with API route names prefixed `api.`.
Run `php artisan route:list --path=api` for all endpoints. Examples:

| Method | Endpoint | Operation |
| --- | --- | --- |
| GET | `/api/ipcrs` | Current user's authorized IPCR screen |
| POST | `/api/ipcrs` | Prepare an IPCR |
| GET | `/api/offices?page=2` | Office list, page 2 |
| POST | `/api/offices` | Create an office |
| PATCH | `/api/offices/{office}` | Update an office |
| DELETE | `/api/offices/{office}` | Delete an office |
| POST | `/api/movs` | Upload MOV with multipart form data |
| POST | `/api/office-targets/{officeTarget}/movs` | Submit assigned-target evidence |
| PATCH | `/api/report-workflow/{type}/{report}/submit` | Submit a report |

The API intentionally uses Laravel's `web` middleware: session cookies, CSRF
protection, and the same authentication, role and record checks as the web
controllers. Send `Accept: application/json` and, for writes, `X-CSRF-TOKEN`
from the page's CSRF meta tag. No database credentials reach the browser.
Multipart updates use POST and Laravel's `_method` field.

## Response contract

This is a server-driven UI API, not a public REST API exposing model records.
Reads return `{ "html": "<rendered authorized page>" }`; the interface updates
the page and initializes its Alpine components. This preserves the existing
Blade screens and avoids serializing extra database attributes.

Successful writes return HTTP 200 with
`{ "redirect": "<next web page URL>", "message": "<status or null>" }`.
The client reads the next page through the API. Existing flashed messages remain
available on that next request. Downloads retain their streaming response.

Errors use JSON and HTTP 401 (signed out), 403 (forbidden), 404 (missing),
419 (expired/missing CSRF token), or 422 (validation). Validation includes an
`errors` object; the browser displays its messages and retains the form input.
Failed mutations are never automatically retried.

## Verification

```text
php artisan test --filter=BrowserApiTest
node --test tests/js/browser-api.test.js
npm run build -- --configLoader runner
```

The runner option avoids an esbuild config-loader directory-access error in
restricted Windows environments.
