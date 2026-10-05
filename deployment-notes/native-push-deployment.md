# Production deployment: free native missing-hours push reminders

This is an incremental runtime patch from 36ccbb9. It assumes previous mobile API,
integer ownership/device-session fixes and Google nonce-exchange patches are already
deployed. It does not contain vendor, .env, Firebase credentials, private test files,
database contents, compiled frontend assets or Flutter code. No composer/npm build
or web asset rebuild is required. firebase/php-jwt must already be installed.

## Before uploading

Back up the database and the existing files listed in the manifest. Preserve APP_KEY.
Set MOBILE_PUSH_ENABLED=false and keep Flutter ENABLE_NATIVE_PUSH=false. Pause the
mobile-push worker during deployment. Upload/extract Laravel-relative files into
`/home/raaingqv/mhp-app` (application root, not public). The timestamped ZIP contains
only changed runtime files. Do not replace production .env with .env.example.

Use php84 as configured on this host; if necessary use:
`PHPRC=/home/raaingqv/mhp-app /opt/alt/php84/usr/bin/php` instead of php84 below.

```sh
cd /home/raaingqv/mhp-app
php84 artisan down
php84 artisan optimize:clear
php84 artisan migrate --force
php84 artisan config:cache
php84 artisan route:cache
php84 artisan queue:restart
php84 artisan up
php84 artisan route:list --path=api/v1/mobile/push
php84 artisan schedule:list
```

Migration: `2026_10_05_180000_create_mobile_push_tables.php`.
It adds nullable workspaces.timezone and creates mobile_push_registration_locks,
mobile_push_devices, mobile_session_mutations and mobile_push_deliveries. Migrate
before serving new routes or starting new workers. Existing workspaces use the
current config hours.timezone fallback. Optional timezone values must be valid IANA
names (e.g. Europe/London); no new workspace timezone-edit UI is included.

## Protected Firebase server setup

Use the Firebase project configured for the native apps, enable FCM HTTP v1, and
give the chosen service account permission to send messages in that project. Download
the service-account JSON into a protected directory outside public/document root,
for example `/home/raaingqv/private/mhp-firebase-service-account.json` (example path,
create it before configuring). Protect owner/worker read access, typically chmod 600
when PHP/worker share the same OS user. Never put this file in Git, the patch, APK
or iOS bundle. Verify PHP OpenSSL support and outbound TLS to Google OAuth/FCM.

```dotenv
MOBILE_PUSH_ENABLED=false
MOBILE_PUSH_FIREBASE_PROJECT_ID=YOUR_FIREBASE_PROJECT_ID
MOBILE_PUSH_FIREBASE_CREDENTIALS=/home/raaingqv/private/mhp-firebase-service-account.json
QUEUE_CONNECTION=database
```

These are environment variables, not shell commands; edit production .env privately.
This project ID and service account are separate from the OAuth Web client used by
MOBILE_GOOGLE_AUDIENCES. Do not change working Google login configuration.
Both scheduler and worker need the same environment, APP_KEY, credentials path and
database. Transport uses encrypted token storage/cache and existing JWT dependency.
Redact FCM token and Authorization from reverse-proxy/APM request capture too.

## Scheduler and worker

Keep one existing minute cron, using the appropriate php84 executable/PHPRC:

```cron
* * * * * cd /home/raaingqv/mhp-app && php84 artisan schedule:run >> /dev/null 2>&1
```

The hourly scheduler creates missing-entry candidates and the minute scheduler
queues due retries/clears expired session registrations, each withoutOverlapping.
Existing premium reminders:send is unchanged. No production force bypass exists.
Run a persistent supervised worker (or hosting provider's managed equivalent):

```sh
php84 artisan queue:work --queue=mobile-push --tries=1 --timeout=45
```

Do not use sync in production. Default database retry_after=90s exceeds timeout=45s;
preserve that ordering on any alternate queue. Configure process stopwaitsecs >45s
and automatic worker restart. The outbox owns at most five exponential/provider
throttling retries. Queue duplicates cannot claim a live 120s lease. Hourly scans
run at/after 18:00 local weekday time, not necessarily exactly 18:00 in fractional
offset zones. Entries added before send suppress reminders. Retries past midnight
skip that date. Email markers are separate and cannot suppress native push.

After server/native Firebase, APNs/icon/channel and permissions are configured,
enable MOBILE_PUSH_ENABLED=true, then config:cache and queue:restart. Keep the
client flag disabled until the designated real-device acceptance run succeeds.
GET/PUT/DELETE route checks and fake tests are not evidence of real FCM delivery.
Network acknowledgement uncertainty is retained as unknown and never automatically
replayed; this avoids potentially duplicating an accepted FCM message after timeout.
Unknown records require investigation, not a blind force-send. Generic provider
configuration errors preserve registration; only typed FCM UNREGISTERED deletes it.

## Handoff and live acceptance

Give the separate Flutter ZIP to the Flutter project. It contains OpenAPI 2.1.0,
the integration guide and flutter-native-push-handoff.md. Implement the setting,
rotation and authenticated/membership-checked tap routing before enabling native
push. Delayed messages must be rejected when user_id differs from the current app
account. No access tokens or website URLs are in push routing data.

Real-device acceptance is NOT executed: Firebase credentials/native setup and an
authorized device are not available in this task. Follow the handoff's test record
on non-production. Verify missing-entry delivery, scheduler replay suppression,
entry added before queued send, revoked session, token rotation and tap routing on
Android/iOS. Capture only safe timestamps, build/environment and outcomes.

Roll back by disabling MOBILE_PUSH_ENABLED and ENABLE_NATIVE_PUSH, config:cache and
queue:restart, stopping the dedicated worker and restoring backed-up runtime files
if needed. Retain the populated schema; do not drop tables as rollback cleanup.

## Local evidence

- Isolated MySQL 8.4.3, 127.0.0.1:13367/mhp_mobile_test: migration applied successfully;
  20 push tests passed, 202 assertions, including two concurrent PHP processes.
  Exactly one claim won; the durable delivery attempt count remained one.
- Fake transport tests cover free registration/dispatch, strict input, restricted
  bearers, rotation/rebinding, ownership, idempotency, timezone/DST/weekends, zero-net
  entry suppression, read-only workspaces, email/push separation, acknowledgement
  vs failure, retry limits and retry rechecks, logout/native/web revocation, secret
  expiry cleanup and safe unexpected-error logging.
- The MySQL test instance is isolated, no production DB was touched, and it was
  shut down after verification. Existing fresh-schema plan_prices migration issue
  was previously worked around only on this empty test instance, as documented in
  prior mobile integration evidence; this new migration itself needed no workaround.
- Full SQLite suite: 346 passed, 9 skipped, 2,811 assertions (355 total tests).
  Pint, OpenAPI generator consistency, git diff whitespace and route caching passed.
  Three push routes and both scheduler commands were confirmed locally.
  No production deployment or real-device send is claimed.

Sources: [Firebase HTTP v1](https://firebase.google.com/docs/cloud-messaging/send/v1-api),
[Firebase error codes](https://firebase.google.com/docs/cloud-messaging/error-codes),
[Flutter receiving](https://firebase.google.com/docs/cloud-messaging/flutter/receive-messages).
