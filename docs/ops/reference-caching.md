# File cache for reference selectors

Employee office selectors and active position-title selectors use the dedicated
Laravel `reference` file store under `storage/framework/cache/reference-data`.
Office labels and position titles expire after five minutes. Stored rows contain
only selector fields, not personnel records or role assignments.

Office visibility and eligibility still use the live existing campus scope; form
submission, singleton-role checks and permissions continue to query current data.
This does not cache office reports/counts, approvals, notifications, ratings or MOV
processing status. The default application cache and queue locks are unchanged.

Office and position model saves/deletes invalidate selector caches immediately and
again after a write transaction commits. Transactional reads bypass the shared
cache so rolled-back values cannot leak. Generation keys prevent an older in-flight
lookup from repopulating the currently used generation. The HRMIS import and
Hinunangan office-sync commands explicitly invalidate after bulk writes.

For direct SQL changes or other bulk scripts that bypass Eloquent events, run:

```powershell
& 'C:\xampp\php\php.exe' artisan cache:clear reference
```

Do not clear all application caches merely to refresh a selector. File caching
assumes one application server; no Redis installation is required. Keep the cache
directory writable by PHP and outside the public document root.
