# Dashboard chart tooltip fix

Apply after the previous dashboard patches (source base 5f4b8e4). This patch shows exactly one tooltip, selects the hovered regular/overtime segment, prioritizes pointer hover over keyboard focus, and keeps edge tooltips within the chart. Existing tooltip appearance and overtime calculations are preserved.

## Upload

Back up the destination files and public/build/manifest.json. In /home/raaingqv/mhp-app run `php84 artisan down`, then upload/extract the ZIP into that Laravel root, preserving its relative paths. Keep existing hashed assets for open browser tabs. Do not overwrite .env or change APP_KEY.

Run `php84 artisan optimize:clear`, `php84 artisan config:cache`, `php84 artisan route:cache`, `php84 artisan view:cache`, then `php84 artisan up`. No migrations, dependency installation or production npm build are required.

If php84 is unavailable, use `PHPRC=/home/raaingqv/mhp-app /opt/alt/php84/usr/bin/php` in its place.

## Verification and rollback

Reload the dashboard. Hover the green and orange portions of Monday and Tuesday: only the corresponding tooltip should appear. Focus a segment, then hover another day: only the hovered tooltip should remain. Check first/last days and narrow screens for clipping. Keep the original dark tooltip styling.

Local evidence: WorkspaceOvertimeTest passed 13 tests / 164 assertions; npm production build passed. Automated Chromium checks passed 18 cases at 1100px, 390px and 320px, including immediate single-tooltip visibility, segment selection, pointer/focus switching and panel bounds. Screenshots were visually reviewed. This is not a production or Firefox verification; the patch has not been deployed.

Rollback: restore the backed-up source files, compiled assets and manifest, then clear/rebuild Laravel caches using the commands above. Do not remove older hashed assets.
