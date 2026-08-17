# Hours Calculator Laravel migration

## Objective and implemented architecture

The legacy CodeIgniter 4 Hours Calculator is implemented in this Laravel 13 application under the `myhourspay` brand. It uses the installed Jetstream Livewire/Blade stack, existing Tailwind application shell, Fortify authentication, and the same private-route middleware as the dashboard. No authentication replacement, public API, team ownership, or second frontend application was introduced.

The module consists of:

- `HoursEntry`, owned by one Laravel `User`;
- dedicated store/update Form Requests;
- `HoursEntryPolicy` plus user-scoped route binding and queries;
- a pure `HoursCalculator` using integer local-clock minutes;
- `HoursController` for calendar, CRUD, reports, CSV, XLSX, and print;
- a native responsive month grid and Jetstream-styled entry dialog;
- direct PhpSpreadsheet 5.x workbook generation; and
- an idempotent Artisan legacy importer with a separate import ledger.

The legacy implementation used CodeIgniter Shield, a service/model/controller structure, client-side FullCalendar/DataTables exports, and temporary legacy identity snapshots. This migration retains its confirmed business rules but normalizes ownership to Laravel users and makes all exports server-authoritative.

## Business rules

- Weeks run Monday through Sunday.
- Weekly target: 2,400 minutes (40:00).
- Default unpaid break: 30 minutes; zero and custom breaks are allowed.
- One non-overnight entry is allowed per user and work date.
- End time must be later than start time. Break must be shorter than gross shift time.
- Notes are optional and limited to 500 characters.
- Raw dates, clock times, break minutes, and notes are stored. Daily/weekly/period totals are derived.
- Durations use integer minutes. Decimal hours are not authoritative.
- Local clock subtraction avoids DST timestamp distortion. Product timezone defaults to `Europe/London` through `APP_TIMEZONE`.
- Reports include only selected dates. Weekly performance uses the complete Monday-Sunday entry set around the selected range; boundary weeks are labelled partial because only part of the week appears as report rows.
- Calendar JSON uses an exclusive `end` input. Reports use inclusive start/end inputs. Both are limited to 366 days.

Configuration is centralized in `config/hours.php`. Operational overrides are `HOURS_DEFAULT_BREAK_MINUTES`, `HOURS_WEEKLY_TARGET_MINUTES`, and `HOURS_TIMEZONE`.

## Schema and deletion decision

`hours_entries` contains `id`, `user_id`, `work_date`, `start_time`, `end_time`, `break_minutes`, nullable `notes`, and timestamps. `user_id` has the same unsigned big-integer type as `users.id`, is indexed and foreign-keyed, and is unique with `work_date`.

The foreign key cascades when a user is permanently deleted. This matches the existing Jetstream account-deletion feature and prevents orphaned private records. If employment-record retention must survive account deletion, change this operational policy before production migration and replace cascade behavior with a documented anonymization or restricted-deletion workflow.

`hours_import_records` records the source label, legacy ID, imported Hours entry, and import time. Native entries never appear in this ledger. This makes repeat imports idempotent and rollback precise without relying on ID ranges.

## Routes and access model

All routes use `web`, `auth:sanctum`, Jetstream authenticated-session, and `verified`, exactly like `/dashboard`:

| Method | Path | Route |
|---|---|---|
| GET | `/hours` | `hours.index` |
| GET | `/hours/events` | `hours.events` |
| POST | `/hours/entries` | `hours.entries.store` |
| PATCH | `/hours/entries/{hoursEntry}` | `hours.entries.update` |
| DELETE | `/hours/entries/{hoursEntry}` | `hours.entries.destroy` |
| GET | `/hours/reports` | `hours.reports.index` |
| GET | `/hours/reports/export/excel` | `hours.reports.excel` |
| GET | `/hours/reports/export/csv` | `hours.reports.csv` |
| GET | `/hours/reports/print` | `hours.reports.print` |

Defense in depth consists of authentication middleware, authenticated-user route binding (foreign entries resolve as 404), model policy checks, user relationship creation, and user-scoped calendar/report/export queries. `user_id` is absent from request rules, forms, event JSON, and editable state. Team membership confers no access. Session web routes retain Laravel CSRF protection. Blade escapes notes; spreadsheet text beginning with `=`, `+`, `-`, or `@` is neutralized and explicitly written as text.

## Legacy user mapping and import

Do not import Shield password hashes, identities, sessions, tokens, groups, or login history into Jetstream. Back up both databases first and configure the read-only legacy connection only in the deployment environment:

```dotenv
LEGACY_DB_CONNECTION=mysql
LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=legacy_database
LEGACY_DB_USERNAME=read_only_user
LEGACY_DB_PASSWORD=secret-from-deployment-store
LEGACY_DB_PREFIX=
```

The importer can build a unique legacy Shield user ID to Laravel user ID map from `auth_identities.secret` and existing Laravel emails. For controlled mapping, place an untracked CSV in `storage/app/private`, with exactly these headings:

```csv
legacy_user_id,laravel_user_id
42,7
```

Never commit this file. Laravel user IDs are validated before import, and conflicting duplicate mappings fail the command.

Dry-run and real import:

```bash
php artisan hours:import-legacy --source-connection=legacy --dry-run
php artisan hours:import-legacy --source-connection=legacy --mapping=hours-user-map.csv --dry-run
php artisan hours:import-legacy --source-connection=legacy --mapping=hours-user-map.csv
```

The report includes users matched/missing, rows considered/imported, existing rows skipped, invalid rows rejected, and unresolved ownership. Null or unmapped owners are never assigned. Invalid shifts and overlong notes are rejected without correction. Valid source timestamps are preserved. Existing native user/date rows are not overwritten. Any unresolved or rejected rows produce a non-zero exit code for deployment gating.

To undo one source import while retaining native entries:

```bash
php artisan hours:import-legacy --source=codeigniter-hours --rollback
```

Use a distinct stable `--source` label when importing multiple source datasets.

## Production deployment order

1. Confirm the user-deletion retention decision above.
2. Back up both Laravel and legacy databases and record row counts.
3. Deploy the tested application commit, `composer.json`, `composer.lock`, and frontend files.
4. Run `composer install --no-dev --optimize-autoloader` on PHP 8.3 or later with the extensions required by PhpSpreadsheet, including ZIP, GD, XML, and mbstring.
5. Run `npm ci && npm run build` or deploy the build artifacts produced by the established release pipeline.
6. Put the application in maintenance mode if the deployment process cannot apply the additive schema atomically: `php artisan down`.
7. Run `php artisan migrate --force`.
8. Run `php artisan optimize`.
9. Verify login, two-factor behavior, profile, logout, dashboard, and an authenticated `/hours` request.
10. Verify or create the intended Laravel users through the existing Jetstream account policy. Do not create Shield accounts in Laravel.
11. Configure a least-privilege legacy connection and prepare the untracked mapping CSV if automatic unique-email mapping is insufficient.
12. Run the legacy import with `--dry-run`; archive its non-sensitive counts and review every unresolved, duplicate, and invalid row.
13. Correct mapping/data decisions and repeat dry-run until it exits successfully.
14. Run the real import once with a stable source label.
15. Reconcile source/import/ledger counts and sample ownership, dates, breaks, notes, and timestamps for multiple users.
16. Smoke-test calendar create/edit/delete, report filtering, CSV, XLSX, print, and cross-account isolation.
17. Exit maintenance mode with `php artisan up`.
18. Monitor application/database logs and retain the legacy backup until reconciliation is signed off.

## Rollback

- Application problem before import: deploy the prior commit and prior Composer lock file; run `composer install --no-dev --optimize-autoloader`. The additive tables may remain unused.
- Failed/incorrect import: run the ledger-based rollback command for the exact source label, correct the mapping, dry-run, and retry. This never deletes native entries.
- Schema rollback before native usage: after backing up, run `php artisan migrate:rollback --step=2`. Do not roll back tables after users have created native records without an approved data-preservation plan.
- XLSX dependency problem: keep CSV and print available, revert the application/dependency commit together, and reinstall from the prior lock file. Do not return CSV or HTML with an `.xlsx` extension.
- Duplicate source run: rerun normally; ledger keys and user/date uniqueness make it idempotent. Investigate skipped counts rather than deleting by ID range.

## Verification checklist

- Guests are redirected from calendar, report, CSV, XLSX, and print routes.
- Authenticated users see Hours in desktop and responsive navigation.
- Two users can save the same date; one user cannot bind, update, delete, query, report, or export the other's entry.
- Default/custom/zero breaks, invalid clocks, no overnight shifts, 40-hour target, signed variance, partial weeks, year boundaries, and UK DST dates are covered by tests.
- XLSX is a real OpenXML workbook with title, user, period, generated time, target, summary, styled/frozen headings, and required daily/weekly columns.
- CSV is streamed UTF-8. CSV/XLSX text is formula-safe. Print omits application navigation and controls when printed.
- Import dry-run writes nothing; actual import is idempotent; rollback removes only ledger-linked imports.

Verification commands:

```bash
composer validate --strict
composer audit
php artisan route:list --path=hours -v
php artisan migrate:status
php artisan test
vendor/bin/pint --test
npm run build
```

No PHPStan/Larastan, JavaScript lint, JavaScript test, or browser-test command is configured in this repository.

## File impact

Added: Hours controller, model, service, policy, requests, export class, import command, configuration, schema/import-ledger migrations, three Blade views, unit/feature/import tests, and this guide.

Updated: `User` relationship, policy/binding registration, private web routes, Jetstream navigation, application product name/timezone, legacy connection configuration, environment example, and Composer dependency files.

## Deferred features and limitations

Live timers, multiple/overnight shifts, payroll/pay calculations, invoices, projects/tasks/clients, team ownership, managers, approvals, productivity scoring, synchronization, subscriptions, and token-authenticated Hours APIs remain intentionally unsupported. Reports render all rows for the bounded range rather than paginate; the 366-day limit bounds memory use. XLSX generation is synchronous and intended for this bounded range. Changing user deletion/retention policy remains a required production decision.
