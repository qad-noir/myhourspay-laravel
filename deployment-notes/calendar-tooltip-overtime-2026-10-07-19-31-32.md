# Monthly calendar tooltip overtime

Apply after the existing dashboard patches (source base 0c3f5a9). Calendar event data now includes daily_overtime_minutes and daily_overtime_formatted, using net worked minutes after paid/unpaid break rules and the workspace daily contract. The existing tooltip adds a daily overtime line, including zero, or Daily overtime not configured for a missing daily contract. Dates visible from adjacent months are included. Weekly report calculations and stored entries are unchanged.

Back up destination files and public/build/manifest.json. In /home/raaingqv/mhp-app run `php84 artisan down`, upload/extract the ZIP into that Laravel root preserving relative paths, then run `php84 artisan optimize:clear`, `php84 artisan config:cache`, `php84 artisan route:cache`, `php84 artisan view:cache`, and `php84 artisan up`. Keep older hashed assets for open browser tabs. No migrations or dependency installs or production npm build are needed. Preserve .env and APP_KEY.

If php84 is unavailable use `PHPRC=/home/raaingqv/mhp-app /opt/alt/php84/usr/bin/php` instead.

Reload the hours calendar and hover a worked day: check the net time, daily overtime, breaks and notes. Check a date visible from the previous month, a zero-overtime day and a workspace without a daily contract. Empty dates should not gain worked-entry tooltips. Existing tooltip styling is unchanged.

Local verification: 15 WorkspaceOvertimeTest tests passed, 178 assertions; production frontend build passed. Regression covers 15m overtime on a prior-month date, 2h overtime, zero overtime and a missing daily contract, even with a Weekly workspace basis. This patch has not been deployed and live browser verification remains required.

Rollback: restore backed-up controller, JavaScript source, assets and manifest, then clear/rebuild Laravel caches as above.

Package: `calendar-tooltip-overtime-2026-10-07-19-31-32.zip`
Source base: `0c3f5a9`
Source HEAD: `38fc1001560726784b5b94565e62c399016e7995`
Files: 6
SHA-256: `f801fcb839c641a05035299476ee12668bb990b9913de5076a7cf534450c279b`

