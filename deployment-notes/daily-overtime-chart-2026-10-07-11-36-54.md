# Daily overtime chart correction

Incremental follow-up to f2e0cc9 (the report/chart patch). Production is not changed
by preparing this upload. No migration, dependency or CSS build change is required.

Each chart date now shows its own excess over contracted daily hours, regardless
of whether the workspace's selected summary basis is Weekly or Daily. With an 8h
daily contract, a 10h Monday immediately shows 2h green overtime, even when the
week remains below its 40h target. Weekly summary/report totals retain their
selected rule. The legend now says Daily overtime. The requested weekly-target
note is removed. No entry, earnings, approval or workspace preference is changed.
Missing daily contract is not guessed: no green segment and the tooltip says
Daily overtime not configured. Existing chart colours/assets are reused.

Back up the files listed in the manifest. At /home/raaingqv/mhp-app:

```sh
php84 artisan down
# Upload/extract the runtime ZIP here, preserving Laravel-relative paths.
php84 artisan optimize:clear
php84 artisan config:cache
php84 artisan route:cache
php84 artisan view:cache
php84 artisan up
```

If php84 is unavailable, use PHPRC=/home/raaingqv/mhp-app
/opt/alt/php84/usr/bin/php. Preserve .env and APP_KEY. Restore the backed-up files
and rebuild caches for rollback. The prior chart CSS/assets must already be installed.

Acceptance: open a workspace with a configured daily contract and a date exceeding
it while total week hours are below the weekly target. The day's green segment
must appear immediately. Verify tooltip minutes, selected summary remains correct,
the removed note is absent, empty days and long shifts still render correctly.

The updated Flutter report prompt supersedes the earlier chronological weekly
allocation instruction. Backend deployment alone does not update Flutter screens.
ZIP entry count, paths and SHA-256 hashes are verified before delivery.

Verification: full SQLite suite365 passed,9 skipped,3014 assertions; the13
overtime feature tests passed with152 assertions. Pint passed and Blade cache
compilation succeeded. MySQL was not rerun for this read-only chart follow-up.
Production deployment and actual Flutter-device verification were not performed.

Package: `daily-overtime-chart-2026-10-07-11-36-54.zip`
Source base: `f2e0cc9`
Source HEAD: `c3f4d8fa857ba05540788dab012922e4c559f172`
Files: 2
SHA-256: `606c68fc1277573036f1cedbb49a5ef005f6061ce67025333ea4ef75f5317065`

