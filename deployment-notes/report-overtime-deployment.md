# Report overtime clarification patch

Incremental patch based on 76cbba3. Requires the 6 October Daily/Weekly overtime
patch already installed. No migrations, Composer dependencies or compiled JS/CSS
updates are added in this follow-up. Production has not been changed by this task.

The attached export records Weekly basis with 8h contracted daily hours. Its
27 dated entries total 249h 15m, daily excess 33h 15m, weekly excess 30h 45m.
The selected Weekly calculation is not an arithmetic bug. This patch makes the
selected report basis explicit, shows the comparison in the report, links directly
to workspace overtime preferences, removes the requested dashboard line and
explains partial weeks in web/Excel/CSV/print reports. No forced global basis change
or record mutation is included. Unrelated workspaces retain their chosen basis.

Back up the destination files listed in the manifest. In /home/raaingqv/mhp-app:

```sh
php84 artisan down
# Upload/extract runtime ZIP here, preserving Laravel-relative paths.
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
Final test results are recorded in the bundle evidence JSON.
