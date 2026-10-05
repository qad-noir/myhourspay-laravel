# Flutter handoff: free native missing-hours reminders (contract 2.1.0)

Keep `ENABLE_NATIVE_PUSH=false` until the backend migration, Firebase server
configuration and native Firebase/APNs configuration are deployed and a real-device
test passes. Registration is implemented locally; actual delivery is unverified.
Use the accompanying mobile.openapi.yaml and mobile-integration.md. Service-account
credentials belong only on the server and are unrelated to MOBILE_GOOGLE_AUDIENCES.

Add an optional account setting "Remind me to log my hours" with supporting text
"A push reminder after 6pm on weekdays when you haven't logged hours in a writable
workspace. Available on every plan." Keep email and promotional consent separate.
It controls only this signed-in native session/device. Web builds do not register.

Use existing bearer auth and API base URL. GET /push/device returns
`{"data":{"enabled":false}}` when no registration exists; an existing registration
returns its real boolean. Never expect a token or registration ID in the response.

After explicit user opt-in, request native notification permission. If denied,
explain how to open OS settings; do not claim reminders are enabled. Obtain the FCM
registration token and PUT /push/device with a new UUID Idempotency-Key:

```json
{"enabled":true,"token":"FCM_REGISTRATION_TOKEN","platform":"android","device_name":"Samsung Galaxy"}
```

Platform is android or ios; enabled is a strict JSON boolean, token is 1–4096
characters, device_name is 1–255. HTTP 200 returns `{"data":{"enabled":true}}`.
Use the same key/body only for an identical retry; changing token/settings requires
a new key. Conflict: 409 idempotency_conflict. Never log tokens, request bodies,
authorization headers or notification payloads.

Disable with a new-key PUT:
`{"enabled":false,"platform":"android","device_name":"Samsung Galaxy"}`.
Omit token entirely, including null. Response: enabled=false. Alternatively
DELETE /push/device unregisters only this session, is repeatable and returns 204
without requiring Idempotency-Key. GET restores preference state after uncertainty.

While opted in, onTokenRefresh registers the rotated token with the CURRENT mobile
session and a new key. Capture the current user/session generation and discard late
permission/token callbacks after logout or account switch. Serialize changes; old
callbacks must not rebind the token away from a newly signed-in account. After
expiry/sign-out, sign in again and refetch the setting; obtain explicit opt-in for
the new session. Best-effort DELETE before logout; never block logout on failure.
Server logout/revocation/expiry stops delivery even if client unregister fails.

FCM data string fields: type=missing_entry, user_id, workspace_id, work_date.
On tap, validate the shape/date, require authentication, compare user_id with the
CURRENT account, fetch and verify workspace membership, then open the week
containing work_date. Reject previous-account or inaccessible-workspace messages.
Fetch current hours: the user may already have added an entry. Do not navigate to
website URLs or put access tokens in payloads. Apply the same checks to
getInitialMessage and onMessageOpenedApp; deduplicate repeated tap events and keep
pending routes isolated to the intended authenticated account.

Android: configure the Firebase app matching package/signing setup, runtime
notification permission where required, monochrome drawable `ic_notification`,
and a reminder notification channel. iOS: Firebase bundle/app configuration,
APNs authentication key in Firebase, entitlements and notification permission.
Follow Firebase's platform and receiving guides. Preserve Google nonce/auth flows.
Choose explicit foreground presentation; avoid duplicate banner/system messages.

Test free-account opt-in, permission denial, rotation, logout/account-switch races,
offline PUT/replay/conflict, disabling, and taps in foreground/background/terminated
states. Use real Android/iOS devices before production rollout. Show actual results
and screenshots; fake FCM tests prove server behaviour only.

## Real-device acceptance record — not executed yet

Record environment URL, app build, OS version, permission state, workspace timezone
and local cutoff, queue/scheduler status, acknowledgement timestamp and whether the
correct authenticated week opened. Never record FCM/auth tokens, emails or full
payloads. Test one missing-entry reminder, repeat scheduler without duplicate send,
entry added before queued send, logout/revoke before send and previous-account tap
rejection. FCM acknowledgement is not evidence of device display or tap success.
