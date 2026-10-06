# Deploy Daily/Weekly workspace overtime

This is an incremental upload for the existing MyHoursPay Laravel deployment,
based on f4502f7 (after the native missing-hours push patch). It does not include
earlier patches. Production has not been modified or verified by this task.

The runtime ZIP preserves Laravel-relative paths and includes PHP, Blade views,
the new migration and canonical OpenAPI 2.2.0 document. No credentials, .env,
vendor, tests, user uploads or compiled frontend assets are included. There are
no new Composer/NPM dependencies and no asset build is required for this patch.

## Deployment

1. Back up the production database and every destination file in the manifest.
   Confirm the prior patches are installed. Compare the ZIP SHA-256 with its
   supplied checksum. Work from `/home/raaingqv/mhp-app`.
2. Run `php84 artisan down`, then upload/extract the ZIP into that Laravel root,
   preserving directories. Do not extract to public/ or the parent directory.
3. Run:

   ```sh
   php84 artisan migrate --force
   php84 artisan optimize:clear
   php84 artisan config:cache
   php84 artisan route:cache
   php84 artisan view:cache
   php84 artisan queue:restart
   php84 artisan up
   ```

   If the php84 shell alias is unavailable, use
   `PHPRC=/home/raaingqv/mhp-app /opt/alt/php84/usr/bin/php` in its place.
   Preserve APP_KEY, .env, existing auth/provider/Firebase configuration and
   existing timezone. No environment variables are added by this patch.
4. Confirm `php84 artisan route:list --path=api/v1/mobile/workspaces` includes
   PATCH `api/v1/mobile/workspaces/{workspace}/settings`.

## Data behavior and acceptance

The migration adds only workspaces.contracted_daily_minutes (nullable) and
workspaces.overtime_basis (default weekly). Existing workspaces remain Weekly.
There is no historical hours-entry backfill, repricing or approval reset.
An owner/administrator can add a daily contract and choose Daily in the website's
Profile > Workspace preferences. Adding a contract alone keeps Weekly selected.
Changing the chosen basis recalculates all historical overtime on reads/exports.
Stored hours, breaks, rates, earnings and approval/entry versions stay unchanged.
Payroll regular/overtime columns reflect the current basis, but stored earnings
are retained. This is not a retroactive payroll payment adjustment.

Deploy the server before enabling the coordinated Flutter setting. The separate
Flutter ZIP includes the updated contract and paste-ready implementation prompt.
Backend deployment alone does not update the Android/iOS screens/calculations.

Use an owned designated test workspace/account for authorized acceptance:

- GET /api/v1/mobile/workspaces: weekly/null defaults, a settings_version and
  can_manage_settings for the owner. Existing mobile auth continues unchanged.
- Set daily contract480 and Daily with the current settings_version and fresh
  UUID Idempotency-Key via PATCH .../workspaces/{id}/settings. 200 returns Workspace.
- For synthetic 10h + four 7h days, verify worked38h, Daily OT2h, Weekly OT0h.
  Do not insert this fixture into a real user's records. Compare a designated
  account's past records with its known calculation and confirm persisted entry/
  approval attributes are unchanged. Switch Weekly and confirm OT0h in the fixture.
- Old settings version returns409 workspace_settings_changed; exact successful
  request replay returns original200 and Idempotency-Replayed:true. Invalid Daily
  without contract returns422 validation_failed. Member cannot change settings.
- Verify web dashboard, report/CSV/XLSX, approved timesheet, and payroll export.
  Daily monthly total excludes adjacent-month dates; Weekly monthly comparison
  retains full intersecting weeks. Never add Daily and Weekly comparison totals.
- Verify Add Hours/edit and locked-timesheet protections continue to work.
  Existing approved/locked records may display recalculated overtime but stay locked.

No production outcome or workplace's reported 34+ hours can be confirmed without
the actual dated hours/break rules and a post-deployment comparison.

## Rollback

Prefer restoring backed-up runtime files while leaving these additive columns
in place. Older code will display Weekly overtime; entries/earnings remain intact.
Do not roll back unrelated migrations. Dropping these columns loses the workspace
daily contract/basis preferences, so do that only through a separately planned
data backup and targeted migration rollback. Restore the prior OpenAPI contract
and disable the new Flutter setting if the backend is reverted.

## Evidence from this task

- Full SQLite suite: 363 passed, 9 skipped, 2963 assertions (372 total).
- Isolated MySQL8.4 on127.0.0.1:13367 / mhp_mobile_test: migration applied;
  WorkspaceOvertimeTest11 passed /101 assertions. These include read-only historical
  recalculation, permissions, stale version/idempotency, approved version stability,
  exact-month boundaries, dashboard invalidation, exports/payroll, scheduled CSV
  and overtime reminder. Test accounts/entries are synthetic and transactionally
  rolled back. Never use the isolated bootstrap against production.
- Deployment route/view cache compilation and existing MySQL API/payroll
  regressions are recorded in the final package result/evidence file.
- Production deployment and Flutter real-device acceptance: not performed.

Package: `workspace-daily-overtime-2026-10-06-18-07-25.zip`
Source base: `f4502f7`
Source HEAD: `36847d507748a1c971839aabffc6071502be1d21`
Files: 32
SHA-256: `524491cfd434013044d7259634cc7db0f95092dd58962f5712fc35d83f650202`

