# Daily overtime for the weekly dashboard card

Follow-up to 332f8ee. “Overtime this week” now sums positive daily excess for
the current Monday–Sunday week using contracted daily minutes, independently
of the workspace's selected report basis. Its caption is “Sum of daily overtime
this week”. Without a daily contract it displays Not configured rather than zero.
The chart already uses this daily rule. Monthly cards and reports retain their
existing selected basis. No recorded entries, preferences or approvals change.

Back up manifest destination files. At /home/raaingqv/mhp-app:

```sh
php84 artisan down
# Extract/upload runtime ZIP here, preserving Laravel-relative paths.
php84 artisan optimize:clear
php84 artisan config:cache
php84 artisan route:cache
php84 artisan view:cache
php84 artisan up
```

No migration, dependency or asset build is required. Preserve .env and APP_KEY.
If php84 is unavailable, use PHPRC=/home/raaingqv/mhp-app
/opt/alt/php84/usr/bin/php. For rollback restore backed-up files and rebuild caches.
The preceding overtime/chart patches must already be installed.

Verification: WorkspaceOvertimeTest13 passed,164 assertions; Pint passed. Fixture
10h Monday plus four7h days, daily contract8h/weekly target40h: card shows2h even
in Weekly mode, with the underlying weekly comparison0h. No daily contract shows
Not configured. Production deployment and real-device acceptance are not performed.
