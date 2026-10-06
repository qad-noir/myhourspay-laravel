"""Build the canonical OpenAPI document (JSON syntax is valid YAML 1.2).

Run from any directory: python scripts/build-mobile-contract.py [--check]
No third-party Python dependencies required.
"""
import json
import pathlib
import sys

root = pathlib.Path(__file__).resolve().parents[1]
ref = lambda name: {"$ref": f"#/components/schemas/{name}"}
text = {"type": "string"}
integer = {"type": "integer"}
boolean = {"type": "boolean"}
date = {"type": "string", "format": "date"}
instant = {"type": "string", "format": "date-time"}
nullable_text = {"type": ["string", "null"]}
nullable_int = {"type": ["integer", "null"]}
version = {"type": "string", "pattern": "^[a-f0-9]{64}$"}
password = {"type": "string", "format": "password", "writeOnly": True, "maxLength": 4096}
email = {"type": "string", "format": "email", "maxLength": 255}
device = {"type": "string", "minLength": 1, "maxLength": 100}
clock = {"type": "string", "pattern": "^([01][0-9]|2[0-3]):[0-5][0-9]$"}


def obj(properties, required=None):
    return {"type": "object", "properties": properties, "required": list(properties) if required is None else required}


def array(items):
    return {"type": "array", "items": items}


def envelope(schema):
    return obj({"data": schema})


schemas = {}
schemas["Error"] = obj({"code": text, "message": text, "errors": {"type": "object", "additionalProperties": array(text)}, "reference": text}, ["code", "message"])
schemas["Message"] = obj({"message": text})
schemas["User"] = obj({"id": integer, "name": text, "email": email, "email_verified": boolean, "two_factor_enabled": boolean, "onboarding_required": boolean, "trial_choice_required": boolean})
schemas["TokenResponse"] = obj({"status": {"type": "string", "enum": ["authenticated", "email_verification_required"]}, "token_type": {"const": "Bearer"}, "access_token": {"type": "string", "description": "Opaque MHP Sanctum device token. Store securely. Never log."}, "expires_at": instant, "user": ref("User")})
schemas["MfaResponse"] = obj({"status": {"const": "two_factor_required"}, "challenge_token": {"type": "string", "minLength": 64, "maxLength": 64}, "expires_at": instant})
schemas["AuthResponse"] = {"oneOf": [ref("TokenResponse"), ref("MfaResponse")]}
schemas["Login"] = obj({"email": email, "password": password, "device_name": device})
schemas["Register"] = obj({"name": {"type": "string", "maxLength": 255}, "email": email, "password": password, "password_confirmation": password, "device_name": device, "terms": {"const": True}, "marketing_consent": boolean}, ["name", "email", "password", "password_confirmation", "device_name", "terms"])
schemas["MfaInput"] = obj({"challenge_token": {"type": "string", "minLength": 64, "maxLength": 64}, "code": {"type": "string", "pattern": "^[0-9]{6}$"}, "recovery_code": {"type": "string", "maxLength": 100}}, ["challenge_token"])
schemas["MfaInput"]["anyOf"] = [{"required": ["code"]}, {"required": ["recovery_code"]}]
schemas["SocialInput"] = obj({"id_token": {"type": "string", "maxLength": 16000, "writeOnly": True}, "device_name": device, "nonce": {"type": "string", "minLength": 64, "maxLength": 64}, "name": {"type": "string", "maxLength": 255}, "terms": {"const": True}}, ["id_token", "device_name"])
schemas["SocialInput"]["description"] = "Google requires challenge_id from /auth/google/challenge and its RAW nonce in the signed ID token; Apple requires nonce from /auth/nonce with its SHA-256 convention. New accounts require name and terms=true. MHP email verification is required for new social accounts. Existing matching emails return account_link_required. Google requires a fresh ID token (iat within 5 minutes); tokens are accepted once."
schemas["SocialLinkInput"] = obj({"id_token": {"type": "string", "maxLength": 16000}, "current_password": password, "nonce": {"type": "string", "minLength": 64, "maxLength": 64}}, ["id_token", "current_password"])
# Google nonce exchange is a coordinated breaking change (contract 2.0).
challenge_id = {"type": "string", "format": "uuid"}
schemas["GoogleChallenge"] = obj({"challenge_id": challenge_id, "nonce": {"type": "string", "pattern": "^[0-9a-f]{64}$"}, "nonce_mode": {"const": "raw"}, "expires_at": instant})
for base, google, apple in [("SocialInput", "GoogleSocialInput", "AppleSocialInput"), ("SocialLinkInput", "GoogleLinkInput", "AppleLinkInput")]:
    original = schemas[base]
    schemas[google] = obj({k: v for k, v in original["properties"].items() if k != "nonce"}, original["required"] + ["challenge_id"])
    schemas[google]["properties"]["challenge_id"] = challenge_id
    schemas[apple] = obj(original["properties"], original["required"] + ["nonce"])
    schemas[base] = {"oneOf": [ref(google), ref(apple)], "description": "Use the Google schema for provider=google and Apple schema for provider=apple. Google challenge_id is mandatory; no legacy token-only fallback."}

schemas["Workspace"] = obj({"id": integer, "name": text, "currency": nullable_text, "default_break_minutes": integer, "default_break_type": {"type": "string", "enum": ["paid", "unpaid"]}, "weekly_target_minutes": integer, "role": text, "writable": boolean, "timezone": text, "features": obj({"clients_projects": boolean, "timesheet_approvals": boolean})})
schemas["PushDevice"] = obj({"enabled": boolean})
push_base = {"enabled": boolean, "platform": {"type": "string", "enum": ["android", "ios"]}, "device_name": {"type": "string", "minLength": 1, "maxLength": 255}}
schemas["PushDeviceInput"] = {"oneOf": [obj({**push_base, "enabled": {"const": True}, "token": {"type": "string", "minLength": 1, "maxLength": 4096, "writeOnly": True}}), {**obj({**push_base, "enabled": {"const": False}}), "not": {"required": ["token"]}}]}
schemas["WorkspaceInput"] = obj({"name": {"type": "string", "minLength": 3, "maxLength": 100}, "position": {"type": "string", "minLength": 3, "maxLength": 100}, "default_break_type": {"type": "string", "enum": ["paid", "unpaid"]}, "default_break_minutes": {"type": "integer", "minimum": 0, "maximum": 1439}, "weekly_target_minutes": {"type": "integer", "minimum": 60, "maximum": 10080}})
hours_input = {"work_date": date, "start_time": clock, "end_time": clock, "break_minutes": {"type": "integer", "minimum": 0, "maximum": 1439}, "break_type": {"type": "string", "enum": ["paid", "unpaid"]}, "notes": {"type": ["string", "null"], "maxLength": 500}, "project_id": nullable_int, "billable": boolean}
schemas["HoursInput"] = obj(hours_input, ["work_date", "start_time", "end_time", "break_minutes", "break_type"])
schemas["HoursInput"]["description"] = "One date per user/workspace. End must be after start; overnight shifts are unsupported. Break must be shorter than the shift even when paid. Non-null project or billable=true requires clients_projects."
schemas["HoursInput"]["example"] = {"work_date": "2026-09-28", "start_time": "09:00", "end_time": "17:00", "break_minutes": 30, "break_type": "unpaid"}
schemas["HoursUpdate"] = obj({**hours_input, "version": version}, schemas["HoursInput"]["required"] + ["version"])
schemas["HoursEntry"] = obj({**hours_input, "id": integer, "workspace_id": integer, "timesheet_id": nullable_int, "net_minutes": integer, "version": version})
schemas["PageMeta"] = obj({"current_page": integer, "last_page": integer, "per_page": integer, "total": integer})
schemas["Week"] = obj({"key": text, "number": integer, "start": date, "end": date, "minutes": integer, "formatted": text, "target_minutes": integer, "target_formatted": text, "variance_minutes": integer, "variance_formatted": text, "partial": boolean})
schemas["HoursPage"] = obj({"data": array(ref("HoursEntry")), "meta": ref("PageMeta"), "summary": obj({"total_minutes": integer, "total_formatted": text, "overtime_minutes": integer, "weeks": array(ref("Week"))})})
schemas["Project"] = obj({"id": integer, "name": text, "client_id": nullable_int})
schemas["Timesheet"] = obj({"id": integer, "workspace_id": integer, "user_id": integer, "status": {"type": "string", "enum": ["draft", "submitted", "approved", "rejected", "locked"]}, "week_start": date, "submission_note": nullable_text, "review_note": nullable_text, "reviewed_by": nullable_int, "submitted_at": {"type": ["string", "null"], "format": "date-time"}, "reviewed_at": {"type": ["string", "null"], "format": "date-time"}, "locked_at": {"type": ["string", "null"], "format": "date-time"}, "updated_at": {"type": ["string", "null"], "format": "date-time"}, "version": version, "user_name": nullable_text, "total_minutes": integer})
schemas["TimesheetDetail"] = {"allOf": [ref("Timesheet"), obj({"entries": array(ref("HoursEntry"))})]}
schemas["Session"] = obj({"id": integer, "device_name": text, "last_used_at": {"type": ["string", "null"], "format": "date-time"}, "expires_at": {"type": ["string", "null"], "format": "date-time"}, "current": boolean})

# Additive workspace overtime settings (2.2). Existing workspaces remain weekly.
basis = {"type": "string", "enum": ["daily", "weekly"]}
daily_contract = {"type": ["integer", "null"], "minimum": 1, "maximum": 1440}
overtime_fields = {"overtime_basis": basis, "contracted_daily_minutes": daily_contract,
                   "daily_overtime_minutes": nullable_int, "weekly_overtime_minutes": integer,
                   "overtime_minutes": integer}
for name in ["Workspace", "Week", "Timesheet"]:
    fields = ({"overtime_basis": basis, "contracted_daily_minutes": daily_contract,
               "settings_version": version, "can_manage_settings": boolean} if name == "Workspace"
              else {"daily_overtime_minutes": nullable_int, "weekly_overtime_minutes": integer,
                    "overtime_minutes": integer, "overtime_formatted": text} if name == "Week"
              else overtime_fields)
    schemas[name]["properties"].update(fields)
    schemas[name]["required"].extend(fields)
schemas["HoursEntry"]["properties"]["daily_overtime_minutes"] = nullable_int
schemas["HoursEntry"]["required"].append("daily_overtime_minutes")
summary = schemas["HoursPage"]["properties"]["summary"]
summary["properties"].update({**overtime_fields, "overtime_formatted": text})
summary["required"] = list(summary["properties"])
schemas["WorkspaceInput"]["properties"].update({"contracted_daily_minutes": daily_contract, "overtime_basis": basis})
schemas["WorkspaceInput"]["description"] = "Optional overtime settings default to weekly and no daily contract. Daily basis requires a positive daily contract."
schemas["WorkspaceSettingsInput"] = obj({"settings_version": version,
    "weekly_target_minutes": schemas["WorkspaceInput"]["properties"]["weekly_target_minutes"],
    "contracted_daily_minutes": daily_contract, "overtime_basis": basis}, ["settings_version"])
schemas["WorkspaceSettingsInput"]["anyOf"] = [{"required": [field]} for field in ["weekly_target_minutes", "contracted_daily_minutes", "overtime_basis"]]
schemas["WorkspaceSettingsInput"]["description"] = "Partial update; omitted values are preserved. Effective daily basis requires a non-null daily contract. To clear a daily contract, switch to weekly in the same request. Applies read-only overtime derivation to historical records; does not change earnings, entry or approval versions."

document = {
    "openapi": "3.1.0",
    "info": {"title": "MyHoursPay Native Mobile API", "version": "2.2.0", "description": "Implemented native routes, replacing the 0.1.0 source-inspection draft. Production URL is the deployment target, not evidence of deployment. No paid external api_access requirement. Existing feature entitlements and workspace permissions apply. See mobile-integration.md for platform setup and release limitations."},
    "servers": [{"url": "http://127.0.0.1:8000/api/v1/mobile", "description": "Local Laravel; Android emulator uses 10.0.2.2 instead"}, {"url": "https://mhp.glsltd.co.uk/api/v1/mobile", "description": "Production target after deployment; do not run automated write tests here"}],
    "security": [{"sanctumBearer": []}], "paths": {},
    "components": {"securitySchemes": {"sanctumBearer": {"type": "http", "scheme": "bearer", "description": "Opaque Sanctum token issued by native auth. Normal device lifetime defaults to 30 days, verification token to 60 minutes. No refresh endpoint. MFA challenge is not a bearer token."}}, "schemas": schemas},
}


def query(name, schema, required=False):
    return {"name": name, "in": "query", "required": required, "schema": schema}


def operation(path, method, name, summary, response=None, body=None, status=200, public=False, params=None, idempotent=False, description=""):
    parameters = list(params or [])
    for segment in path.split("/"):
        if segment.startswith("{"):
            key = segment[1:-1]
            parameters.append({"name": key, "in": "path", "required": True, "schema": {"type": "string", "enum": ["google", "apple"]} if key == "provider" else {"type": "integer", "minimum": 1}})
    if idempotent:
        parameters.append({"name": "Idempotency-Key", "in": "header", "required": True, "schema": {"type": "string", "format": "uuid"}, "description": "Generate one UUID per logical write; reuse it only for an identical retry. Replays return the stored original success. Changed payload/path/method with the same key returns 409."})
    responses = {str(status): {"description": "Success"}}
    if response is not None:
        responses[str(status)]["content"] = {"application/json": {"schema": response}}
    for code, label in [(401, "Unauthenticated"), (403, "Account, verification, feature or role restriction"), (404, "Resource not found or inaccessible"), (409, "Conflict"), (422, "Invalid input or credentials"), (429, "Rate limited"), (500, "Server error"), (503, "Provider or service unavailable")]:
        responses[str(code)] = {"description": label, "content": {"application/json": {"schema": ref("Error")}}}
    responses["429"]["headers"] = {"Retry-After": {"description": "Seconds until retry, when supplied by the throttle", "schema": integer}}
    result = {"operationId": name, "summary": summary, "description": description, "tags": ["Authentication" if path.startswith('/auth') or path == '/me' else "Workspaces"], "responses": responses}
    if parameters:
        result["parameters"] = parameters
    if public:
        result["security"] = []
    if body:
        result["requestBody"] = {"required": True, "content": {"application/json": {"schema": body}}}
    document["paths"].setdefault(path, {})[method] = result


operation('/auth/providers', 'get', 'getAuthProviders', 'Provider availability', envelope(obj({'google': boolean, 'apple': boolean, 'google_challenge_required': {'const': True}})), public=True)
operation('/push/device', 'get', 'getPushDevice', 'Read native reminder preference for this mobile session', envelope(ref('PushDevice')), description='Verified native mobile:access bearer required. Free on every plan. Returns enabled=false if no registration. No registration token or hash is returned.')
operation('/push/device', 'put', 'putPushDevice', 'Register, rotate or disable native missing-entry reminders', envelope(ref('PushDevice')), ref('PushDeviceInput'), idempotent=True, description='Strict JSON boolean. Enabling requires token; disabling must omit token. Bind to authenticated user and current mobile Sanctum session only. Replays are scoped to session and return original response. Free missing-entry push only; no email/marketing consent changes. Server stores FCM token encrypted and rebinds duplicate token hashes away from previous sessions. Account verification required.')
operation('/push/device', 'delete', 'deletePushDevice', 'Unregister current mobile session push device', status=204, description='Safe to repeat. Only current authenticated mobile session is affected. Idempotency-Key is not required. Logout, token revocation and expiry also invalidate delivery.')
operation('/auth/google/challenge', 'post', 'createGoogleChallenge', 'Start nonce-bound Google authentication', ref('GoogleChallenge'), status=201, public=True, description='No body or bearer required. Throttled to 10/minute per IP. Cryptographically random nonce expires in five minutes. Pass nonce UNCHANGED to the per-attempt native Google adapter; do not hash it. Exchange the resulting signed ID token plus challenge_id. Also used for explicit linking. Responses are private/no-store; never log nonce or token.')
operation('/auth/nonce', 'post', 'createAppleNonce', 'Start Apple sign-in', obj({'nonce': text, 'expires_at': instant}), public=True, description='Send SHA-256(raw nonce) to Apple as nonce. Submit raw nonce to MHP with the Apple ID token. Expires after five minutes; single use.')
operation('/auth/{provider}', 'post', 'exchangeProviderCredential', 'Exchange Google or Apple identity', ref('AuthResponse'), ref('SocialInput'), public=True, description='Google requires a fresh per-attempt challenge and a signed RAW nonce match. iat within five minutes and all signature/issuer/audience/expiry/replay checks remain mandatory. A successful exchange consumes challenge and credential atomically, including when MFA or email verification is next. New accounts require name and terms=true. Older apps without nonce support must upgrade.')
operation('/auth/providers/{provider}/link', 'post', 'linkProvider', 'Link identity to authenticated account', ref('Message'), ref('SocialLinkInput'), description='Requires verified mobile token and current MHP password. Google requires challenge_id and signed raw nonce from /auth/google/challenge. Apple requires nonce from /auth/nonce. Never merges accounts by email. Set a password via recovery if originally registered socially.')
operation('/auth/login', 'post', 'login', 'Password sign-in', ref('AuthResponse'), ref('Login'), public=True)
operation('/auth/register', 'post', 'register', 'Create account', ref('TokenResponse'), ref('Register'), status=201, public=True)
operation('/auth/two-factor', 'post', 'completeTwoFactor', 'Complete MFA challenge', ref('TokenResponse'), ref('MfaInput'), public=True, description='Five-minute single-use challenge; at most five failed attempts. Recovery codes are consumed. Password or MFA changes invalidate pending challenges.')
operation('/auth/forgot-password', 'post', 'forgotPassword', 'Send existing password reset email', ref('Message'), obj({'email': email}), public=True, description='Always returns generic success. The current email links to the existing web reset page; native deep links are not configured.')
operation('/auth/reset-password', 'post', 'resetPassword', 'Reset password with emailed token', ref('Message'), obj({'email': email, 'token': text, 'password': password, 'password_confirmation': password}), public=True, description='Successful reset revokes all Sanctum tokens. Does not sign in or bypass MFA.')
operation('/me', 'get', 'getMe', 'Account and onboarding state', envelope(ref('User')), description='Accepts either normal or verification-only mobile tokens.')
operation('/auth/email/verify', 'post', 'verifyEmail', 'Verify email and replace device token', ref('TokenResponse'), obj({'code': {'type': 'string', 'pattern': '^[0-9]{6}$'}}), description='Accepts verification-only token. Replace the old stored token with access_token from this response.')
operation('/auth/email/resend', 'post', 'resendVerification', 'Resend verification code', ref('Message'), description='Accepts verification-only token. Existing MHP resend cooldown applies.')
operation('/auth/session', 'delete', 'logout', 'Revoke current device token', status=204, description='Accepts normal or verification-only token. Offline client logout clears local credentials but cannot revoke the server token until connected.')
operation('/auth/sessions', 'get', 'listSessions', 'List own mobile device tokens', envelope(array(ref('Session'))))
operation('/auth/sessions/{session}', 'delete', 'revokeSession', 'Revoke own mobile device token', status=204)
operation('/workspaces', 'get', 'listWorkspaces', 'List memberships and capabilities', envelope(array(ref('Workspace'))), description='Workspace choice is client-local. Send workspace ID in resource paths; no mutable server current-workspace dependency.')
operation('/workspaces', 'post', 'createWorkspace', 'Create workspace / complete onboarding', envelope(ref('Workspace')), ref('WorkspaceInput'), status=201, description='Enforces workspace limit and unique name per membership. No idempotency support on this endpoint; do not blindly retry after an uncertain response. Refresh workspace list first.')
workspace = '/workspaces/{workspace}'
operation(workspace + '/settings', 'patch', 'updateWorkspaceSettings', 'Update workspace overtime settings', envelope(ref('Workspace')), ref('WorkspaceSettingsInput'), idempotent=True, description='Verified mobile access, writable workspace and owner/administrator required. UUID Idempotency-Key and settings_version are mandatory. Stale version: 409 workspace_settings_changed; reused key with different body: 409 idempotency_conflict. Invalid effective settings: 422 validation_failed. Retry uncertain requests with the exact body and key. Refresh historical summaries after success; no records are backfilled.')
operation(workspace+'/hours', 'get', 'listHours', 'List own hours and period totals', ref('HoursPage'), params=[query('start', date, True), query('end', date, True), query('page', {'type': 'integer', 'minimum': 1}), query('per_page', {'type': 'integer', 'minimum': 1, 'maximum': 100, 'default': 50})], description='Inclusive work-date range; end-start <=366 days. Ascending work_date and id. Summary covers the full requested range, not just the page. Weeks without entries are omitted. Partial-week totals do not imply a full-week total.')
operation(workspace+'/hours', 'post', 'createHours', 'Create hours entry', envelope(ref('HoursEntry')), ref('HoursInput'), status=201, idempotent=True)
operation(workspace+'/hours/{entry}', 'patch', 'updateHours', 'Update own hours entry', envelope(ref('HoursEntry')), ref('HoursUpdate'), idempotent=True, description='Send all required HoursInput fields plus current version. Approved/locked source and destination weeks cannot change. Stale version returns entry_changed (409).')
operation(workspace+'/projects', 'get', 'listProjects', 'List active projects', obj({'data': array(ref('Project')), 'meta': ref('PageMeta')}), params=[query('page', {'type': 'integer', 'minimum': 1})], description='Requires clients_projects entitlement; 100 records per page.')
operation(workspace+'/timesheets', 'get', 'listTimesheets', 'List own timesheets or review queue', obj({'data': array(ref('Timesheet')), 'meta': ref('PageMeta')}), params=[query('scope', {'type': 'string', 'enum': ['mine', 'review'], 'default': 'mine'}), query('page', {'type': 'integer', 'minimum': 1})], description='Requires timesheet_approvals. Review scope requires owner, administrator or manager role and includes all statuses. 50 records per page.')
operation(workspace+'/timesheets/{timesheet}', 'get', 'getTimesheet', 'Timesheet detail with hours', envelope(ref('TimesheetDetail')), description='Owner of timesheet or workspace reviewer; requires timesheet_approvals.')
operation(workspace+'/timesheets', 'post', 'submitTimesheet', 'Submit own weekly timesheet', envelope(ref('Timesheet')), obj({'week_start': date, 'submission_note': {'type': ['string', 'null'], 'maxLength': 2000}}, ['week_start']), status=201, idempotent=True, description='Requires timesheet_approvals and writable workspace. Normalizes date to Monday. Requires at least one entry. Approved/locked weeks must first be reopened.')
operation(workspace+'/timesheets/{timesheet}/review', 'post', 'reviewTimesheet', 'Approve, reject or reopen a timesheet', envelope(ref('Timesheet')), obj({'decision': {'type': 'string', 'enum': ['approved', 'rejected', 'reopened']}, 'review_note': {'type': ['string', 'null'], 'maxLength': 2000}, 'version': version}, ['decision', 'version']), idempotent=True, description='Requires reviewer role, writable workspace and timesheet_approvals. Approve/reject only submitted sheets. Version includes current entry contents; reload after any hours or status change.')

for path_name in ['/auth/{provider}', '/auth/providers/{provider}/link']:
    responses = document['paths'][path_name]['post']['responses']
    responses['422']['description'] = 'validation_failed (missing/malformed challenge_id or other fields); invalid_provider_credential (signature/issuer/audience/expiry/freshness); google_challenge_invalid; google_challenge_expired; google_nonce_mismatch; invalid_nonce (Apple); invalid_credentials (link password); provider_email_required.'
    responses['409']['description'] = 'google_challenge_used; credential_already_used (exchange); account_link_required; account_link_conflict (link). Start a new challenge/native attempt; never replay credentials.'
path = root / 'docs/api/mobile.openapi.yaml'
content = json.dumps(document, indent=2, ensure_ascii=False) + '\n'
if '--check' in sys.argv:
    if not path.exists() or path.read_text(encoding='utf-8') != content:
        raise SystemExit('Contract is stale; run python scripts/build-mobile-contract.py')
    print('Contract matches generator.')
else:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding='utf-8')
    print(f'Wrote {path} ({sum(len(v) for v in document["paths"].values())} operations)')
