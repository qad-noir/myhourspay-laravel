# Beta notices, compact records and public legal pages

Apply this incremental patch after the earlier billing lifecycle, payroll currency and select-control patches. Copy the ZIP contents into the application root while preserving paths. It includes compiled production assets. No migration, dependency installation or Stripe change is required.

After copying:

```sh
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Retain older hashed assets until existing browser sessions have refreshed. This patch does not change trial dates, renewal rules, grants or Stripe settings. The first paid-enforcement activation still creates existing launch grants; their expiry never postpones a subscription charge.

## Changes to verify

- With enforcement off, billing uses beta copy and still shows existing subscription prices and renewal information.
- With enforcement on, customer pages show trial/launch access notices. Trial renewal is primary when launch access overlaps. Members see owner-contact guidance without billing amounts. Paid access of the same or higher tier suppresses redundant launch-expiry notices after renewal.
- Detailed report records load through `/hours/reports/data`, using `range_start`/`range_end` for dates so DataTables' numeric `start` offset cannot overwrite them. Existing date/project/client/billable checks are enforced. Summaries and exports retain the selected period independently of table search.
- Admin support and user/workspace hours histories load through protected endpoints under `/admin/data`. Records use a maximum page size of 100. Derived columns marked non-orderable retain their existing calculations.
- `/terms` and `/policy` use the public site styling. Legacy legal URLs redirect there. Registration consent settings are unchanged while the legal text remains a draft.

## Legal content is a separate delivery

The deployable ZIP deliberately excludes `resources/markdown/policy.md`, `resources/markdown/terms.md` and `LEGAL_DRAFT_COMPLETION.md`. They are in the separate legal-drafts ZIP. Public pages render whatever Markdown is already deployed; this code patch does not replace it with unfinished text. Complete and review the marked facts in the legal package before copying its Markdown to production.

## Validation

- Full suite: 183 tests, 176 passed, 7 skipped, 955 assertions.
- Nine new tests cover trial/grant expiry, member privacy, public legal routes, data isolation, filters, escaping, response limits, full-week overtime and admin access controls.
- A 150-record report fixture verifies pagination and full scoped admin history. A 10,000-record generator verifies summary-only peak-memory growth stays below 8 MiB.
- Blade compilation and production build passed. Vite retains the existing warning about a JavaScript chunk above 500 kB.
- Browser automation returned `Transport closed`; authenticated desktop/mobile screenshot verification remains outstanding. Smoke-test the banner at mobile width, DataTables search/paging/retry and Livewire navigation, and long legal text on the deployed site.

## Reuse and rollback

Use `<x-access-notice>` with an optional workspace for customer access messaging, and `<x-compact-table>` with server-defined columns and an authorised data endpoint for compact record lists. `CompactTable` sets pagination limits and server-owned ordering/search rules. Do not initialise a large preloaded HTML table as a substitute for server-side pagination.

Rollback by restoring the previous versions of changed files and the matching build manifest/assets, then clearing and rebuilding Laravel caches. There is no database rollback step.
