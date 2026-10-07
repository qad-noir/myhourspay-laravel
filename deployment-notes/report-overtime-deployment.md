# Report overtime clarification patch

Incremental patch based on 76cbba3. Requires the 6 October Daily/Weekly overtime
patch already installed. No migrations or Composer dependencies are added.
This follow-up includes built chart CSS, the Vite manifest and every referenced
asset. Production has not been changed by this task.

The attached export records Weekly basis with 8h contracted daily hours. Its
27 dated entries total 249h 15m, daily excess 33h 15m, weekly excess 30h 45m.
The selected Weekly calculation is not an arithmetic bug. This patch makes the
selected report basis explicit, shows the comparison in the report, links directly
to workspace overtime preferences, removes the requested dashboard line and
explains partial weeks in web/Excel/CSV/print reports. No forced global basis change
or record mutation is included. Unrelated workspaces retain their chosen basis.
The dashboard chart stacks regular hours in orange and overtime in green, with a
legend and accessible tooltip totals. Daily mode uses the daily contract. Weekly
mode allocates the first weekly target minutes chronologically to regular hours,
then the remaining minutes to overtime. This visual allocation does not write
payroll records. Bars scale to include long shifts and empty days have no fake bar.

Back up the destination files listed in the manifest. In /home/raaingqv/mhp-app:

```sh
php84 artisan down
# Upload/extract runtime ZIP here, preserving Laravel-relative paths.
# Include public/build/manifest.json AND all supplied public/build/assets files.
php84 artisan optimize:clear
php84 artisan config:cache
php84 artisan route:cache
php84 artisan view:cache
php84 artisan queue:restart
php84 artisan up
```

Use PHPRC=/home/raaingqv/mhp-app /opt/alt/php84/usr/bin/php if php84 is unavailable.
Do not replace .env or APP_KEY. Do not extract into public/. Restore backed-up files
and clear/rebuild caches if rollback is needed. This patch does not update the
production database's selected overtime preferences.
Retain older hashed assets for any browser tabs still using the previous manifest.
No npm build is required on production: the supplied assets were built locally.

After deployment, the owner must select the intended workspace, open Reports >
Change overtime calculation (or Profile > Workspace preferences), choose Daily,
retain 8 contracted daily hours and save. Regenerate 1 September–31 October 2026: main overtime
should show 33:15 for the same 27 entries. A later new/edited entry changes totals.
Weekly remains 30:45 for this fixture; do not add the comparison totals together.
Verify existing entries/earnings/approvals remain unchanged. No live account was
accessed for this task. Coordinate Flutter using the separate handoff archive.

Partial means the date range excludes part of a Monday–Sunday week. The report
starts Tuesday 1 September 2026, so W 36 (31 August–6 September) is partial. It is not
an incomplete record. In Weekly mode the website compares full-week totals; Daily
overtime includes only selected dates. A boundary week need not appear if empty.

The regression uses only dates/times/break minutes from the supplied workbook;
no names, workspace names, notes or original workbook are packaged. It verifies
the same selected Daily total on the website, exports and native API, plus entry
preservation and dashboard removal. ZIP paths/hashes are verified by the packager.
Final verification: full SQLite suite365 passed,9 skipped,3009 assertions;
isolated local MySQL8.4 WorkspaceOvertimeTest13 passed,147 assertions, including
the workbook reconciliation and chart allocation/long-shift regression.
`npm run build` succeeded; the existing chunk-size notice is informational.
Pint and the OpenAPI generator check passed. Production and real Flutter-device
verification were not performed. The bundle evidence JSON records the same results.
