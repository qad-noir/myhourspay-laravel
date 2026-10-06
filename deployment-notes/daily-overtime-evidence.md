# Daily overtime verification — 6 October 2026

Source implementation commit: c1bae67. Contract/handoff commit: b3df770.

`php artisan test --compact`: 372 tests total; 363 passed, 9 skipped;
2963 assertions. No failing tests. Skipped tests include opt-in infrastructure
concurrency/provider checks; this result is not live Android/iOS evidence.

Local MySQL8.4.3 on127.0.0.1:13367, isolated mhp_mobile_test schema:
new2026_10_06_090000 migration applied successfully. Same existing schema/bootstrap
as previous native API tests; no production connection used.

- WorkspaceOvertimeTest:11 passed,101 assertions.
- MobileStringIdsTest + BusinessPlatformTest:36 passed,256 assertions.
- Combined MySQL:47 passed,357 assertions.
- All test users/hours are synthetic; feature tests use transactions. Server was
  stopped after testing. The MySQL bootstrap intentionally refuses any host/port/
  database other than this isolated test target. Existing schema preparation caveat
  about the old plan_prices FK-support index is in mysql-mobile-bootstrap.php;
  no production schema workaround was introduced by this patch.

Verified behaviors: weekly/null defaults, daily contract and basis validation,
owner/administrator isolation, stale settings version and idempotency replay,
read-only historical recalculation, stable approved timesheet/entry data, monthly
boundaries, independent per-user daily thresholds, cache invalidation, CSV/XLSX/
print and approved payroll regular/overtime classification with unchanged stored
earnings, scheduled CSV and existing premium overtime reminder.

`php vendor/bin/pint --dirty --test`: passed.
`python scripts/build-mobile-contract.py --check`: matched;30 operations.
`git diff --check`: passed.
`php artisan route:cache` and `php artisan view:cache`: succeeded.
Cached route list included the new PATCH /workspaces/{workspace}/settings;
route and view caches cleared afterwards for local development.

The packaging script validates ZIP entry count, Laravel-relative paths and each
entry's SHA-256 against its source file, then writes the ZIP SHA-256/manifest.
It excludes .env, tokens, test data, vendor, uploads and private service accounts.

No production upload, real Flutter build or real-account acceptance was performed.
The reported workplace34+ hour discrepancy is not independently reconstructed
without its actual hours/breaks; the confirmed daily-vs-weekly rule is tested with
explicit fixtures. Follow deployment instructions for authorized final acceptance.
