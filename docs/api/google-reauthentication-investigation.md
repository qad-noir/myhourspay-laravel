# Google reauthentication investigation — 3 October 2026

## Evidence and limits

Build 8 reportedly sent a Google credential with unverified ageSeconds=3122,
expiresInSeconds=478 and expectedAudience=true at 13:30 Europe/London (12:30 UTC).
The existing server requires age <=300 seconds as well as expiration, signature,
issuer and audience checks. SDK signOut is not proof of a new credential.

A locally signed fixture with those offsets reproduces iat_too_old. This establishes
the server's behaviour for that input, NOT the reason for the real production event.
No real token or personal claims were captured, and none are required for diagnostics.

Read-only production probe: HTTP Date was 2026-10-03 12:38:15 GMT; local request
interval was 12:38:07.8036344Z–12:38:12.4762373Z. The response timestamp was about
2.5–7.2 seconds ahead of this machine, not 52 minutes. This compares two machines,
not an authoritative clock, and proves neither PHP/NTP synchronization nor the
historical token's rejection reason. The effective production audience remains
unverified until the server-side command is run. No live account journey was run.

## Deployed diagnostics

Run `php84 artisan mobile:identity-diagnostics` on the deployed application.
The command reports an exact-match boolean against the single expected Google
audience 69237986520-s7mltqvpk5aqt3l2pljgemcmr8vtnvb1.apps.googleusercontent.com,
server UTC, and JWT dependency availability. It prints no credentials.
Check `timedatectl status` or `chronyc tracking` with the hosting provider to establish
NTP synchronization. A UTC timestamp by itself is not a synchronization check.

Rejections go to storage/logs/mobile-identity-YYYY-MM-DD.log, retained for seven days.
At most one event per provider/reason per 60 seconds with a shared persistent cache.
The provider/reason cache keys are fixed vocabulary, not user/token identifiers.
Public errors remain 422 invalid_provider_credential without internal reasons.
Only a reason enum and optional integer offsets are explicitly logged; offsets
are clamped to +/-86400 seconds and come only from signature-verified claims.
No token, bearer, subject, email, name, full claims, exception or request is logged.
Normal log timestamps locate the event; sampling can suppress repeated attempts.

Reasons: malformed_token, algorithm_invalid, key_id_invalid, key_unknown,
signature_invalid, key_or_crypto_invalid, jwks_unavailable, jwks_invalid,
issuer_mismatch, audience_mismatch, subject_type_invalid, subject_value_invalid,
timing_invalid, token_expired, iat_too_old, iat_future, not_yet_valid.
Library-level malformed numeric claims may be rejected as malformed_token before
signature verification; these never carry timing. Future iat can indicate a future
credential or a lagging server clock, not proof of either. Firebase's existing
future-time validation is preserved; its default leeway can reject even smaller
future offsets before the application's explicit +30-second upper bound.

Example *synthetic*, not observed production evidence:
{"reason":"iat_too_old","age_seconds":3122,"expires_in_seconds":478}

## Key rotation fix

Known RS256 keys remain cached for one hour. Unknown kid triggers a same-request
refresh/retry, at most once per provider per 60 seconds. Cold requests may perform
the initial load and one rotation refresh. Keys come only from fixed provider URLs.
Invalid refresh responses do not overwrite good cached keys. Bad signatures,
expired tokens and old iat no longer evict valid keys. Tests prove these bounds
with mocked JWK responses, not live production cache rotation.

## Proposed explicit protocol update — NOT implemented or deployed

If verified production logs confirm SDK reuse and the adapter cannot reliably
obtain fresh credentials, introduce a versioned Google challenge flow rather than
relaxing freshness or deleting replay records. Proposed v1.1 addition:

1. POST /auth/google/challenge creates a short-lived cryptographically random nonce
   and opaque challenge ID, returning challenge_id, nonce and expires_at. Store only
   the nonce hash plus provider/purpose and expiry; throttle issuance.
2. A nonce-capable native Google adapter passes SHA-256(nonce) as its nonce parameter.
   Verify each platform's actual behaviour; ordinary google_sign_in.authenticate
   must not be assumed to expose this capability.
3. POST /auth/google adds challenge_id and nonce. Verify RS256, issuer, audience,
   exp and the existing five-minute iat window, then require the signed nonce claim
   to equal SHA-256(nonce). Bind the stored challenge to Google/login purpose.
4. Consume challenge atomically with credential replay consumption/account handling.
   Never accept a reused challenge/credential. Keep explicit account linking, MHP
   MFA, custom email verification and device-token issuance unchanged.
5. Roll out behind an explicit advertised capability and update the canonical
   OpenAPI contract/Flutter adapter together. Do not silently change Build 8's
   current flow or claim a nonce makes an old token acceptable.

Required tests before that proposed contract ships:
- Fresh signed token with matching unconsumed challenge succeeds exactly once.
- Missing/mismatched/expired/provider-swapped nonce or challenge fails closed.
- Two concurrent exchanges have at most one successful consumption.
- Cached old token fails even if accompanied by a new challenge.
- Forged signature, wrong audience/issuer, expired/future/old iat still fail.
- Existing account linking, MFA and email verification branches remain intact.
- Real Android/iOS adapter evidence confirms the nonce claim and fresh iat without
  logging token/claims; create -> verify -> logout -> Google login works against a
  designated non-production backend/account. Mock fixtures alone are insufficient.

This diagnostics patch does not introduce this proposed contract or claim to fix
cached Google credentials. It makes the real rejection attributable and fixes
bounded same-request JWK rotation while retaining the existing security policy.

Validation: 17 focused tests passed (260 assertions); full SQLite suite 313 passed,
7 skipped (2457 assertions). OpenAPI generator check and Pint passed. No contract
change is deployed. Sources for the proposed native nonce capability and Google's
required verification checks:
- https://developers.google.com/identity/android-credential-manager/android/reference/com/google/android/libraries/identity/googleid/GetGoogleIdOption.Builder
- https://developers.google.com/identity/gsi/web/guides/verify-google-id-token
