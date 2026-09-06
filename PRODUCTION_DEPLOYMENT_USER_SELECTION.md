# User selection refinement

Apply after the admin controls patch. Copy these files to the application root, keeping their paths. Compiled assets are included; no migration is needed. Run `php artisan optimize:clear` after deployment and refresh the browser.

User search controls now have no dropdown arrow. The Selected badge, name and email sit in a compact green selection card; the surrounding input stays neutral. Shared remote selection cards use this styling. Ordinary select arrows remain available.

The shared user-search initializer applies `user-search-control` automatically to existing and future fields using `data-admin-user-select`.

Smoke check: select and replace a user on `/admin/billing/grants/create`, then check a long email at mobile width. Browser automation was unavailable in this session, so visual verification remains a deployment check.
