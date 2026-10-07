# Flutter prompt: correct selected overtime and explain partial weeks

Continue the existing MyHoursPay Flutter app. Read applicable AGENTS.md and the
supplied OpenAPI 2.2.0, mobile-integration.md and flutter-daily-overtime-prompt.md.
Implement the following correction without replacing working authentication,
native Google nonce exchange, push, entry editing or approvals.

The supplied website workbook contains an 8h daily contract but selects Weekly.
Its 27 entries have 14955 net minutes (249h 15m). With daily contract 480 minutes,
Daily overtime is 1995 minutes (33h 15m); Weekly overtime is 1845 minutes (30h 45m).
These are two different valid calculations. Do not force all workspaces to Daily,
change the default for existing workspaces or silently switch basis just because
a daily contract exists. This workspace must explicitly select Daily to use 33h 15m
as its main overtime total. Do not hardcode these values into live screens.

1. Fetch workspace.overtime_basis and contracted_daily_minutes. Clearly label
   the main report total “Daily overtime” or “Weekly overtime”. Use server
   summary.overtime_minutes from GET /workspaces/{id}/hours for the entire range,
   never a client weekly-only formula, a sum of repeated page summaries or repeated
   weekly totals in individual rows. Show comparisons only inside the report/
   preferences view when useful. Remove the overview/dashboard diagnostic line
   “Overtime basis: Weekly · Daily overtime this month: ... · Weekly overtime
   across full weeks: ...”; keep the main overtime card.

2. Provide “Change overtime calculation” from the report to the existing workspace
   settings screen, prefilled from the latest server workspace. Owners/admins
   with can_manage_settings can save Daily using PATCH
   /workspaces/{id}/settings with current settings_version, overtime_basis:daily,
   and contracted_daily_minutes:480 for this 8h setting. Use a new UUID
   Idempotency-Key, bearer auth and existing conflict/error handling. Retry an
   uncertain request with its exact key/body. On 409 workspace_settings_changed,
   refresh and let the user review before saving. Do not write another workspace,
   copy personal credentials or create a duplicate API route.

3. On success replace cached workspace data and refetch report/overview/hours/
   timesheets. Returning from settings must update the displayed basis and total.
   Refetch on resume when preferences may have changed on the website. Ordinary
   members may view totals but cannot save settings. Keep existing Weekly choices.
   A missing daily contract displays “Not configured”; require one for Daily.

4. Partial=true means the selected date range starts after the week's Monday or
   ends before its Sunday. It does not mean a broken or incomplete entry and it
   does not mean the week lacks seven worked days. Example:1 September 2026 isTuesday;
   the 31 August–6 September week is marked partial in a 1 September–31 October report. Tooltip/help text:
   “The selected dates exclude part of this week. Daily overtime uses only the
   selected dates.” Native hours summaries use the requested range. If matching
   website full-week Weekly reports/monthly comparisons, use a separate expanded
   Monday–Sunday request and label that scope. Do not inflate daily overtime with
   expanded-range records or prorate the weekly target.

5. Update the dashboard chart to split regular hours and overtime with distinct
   colours: regular orange #FF6B35, overtime green #168456. Add a visible legend
   and tooltip/accessibility labels with both minute totals; colour is not the
   only distinction. In Daily mode each date uses max(0, net_minutes minus daily
   contract). In Weekly mode, for the complete Monday–Sunday week, allocate the
   first weekly_target_minutes chronologically to regular hours and show the
   remaining minutes as overtime on later dates. This is only a chart allocation,
   not a new payroll/entry attribution. Explain “Weekly overtime is shown on the
   days after the weekly target is reached.” Do not mark an entire day as overtime
   just because it reaches 8h. Stack segments proportionally and scale the chart
   to at least its largest bar, so shifts over 10h are not clipped. Empty days
   have no fake coloured bar. Segment overtime summed across the complete week
   must match the server's selected weekly total. Test both modes and 12h shifts.

6. Keep read-only derivation: never alter entry dates, times, breaks, earnings,
   approval status or opaque entry/review versions when changing overtime basis.
   Keep existing month at a glance design. For selected Daily monthly overtime use
   exact-month daily_overtime_minutes; for selected Weekly use full intersecting
   weeks' weekly_overtime_minutes as documented. Do not add these alternatives.

7. Test API parsing, settings navigation/save/conflict, cache refresh and selected
   basis. Recreate synthetic dates/times from the Laravel regression
   WorkspaceOvertimeTest::test_attached_report_fixture_reconciles_daily_and_weekly_totals_across_web_exports_and_mobile,
   excluding personal identifiers/notes. Assert 249:15 net,33:15 Daily,30:45 Weekly,
   with unchanged entries. Add a boundary-week case, paid/unpaid breaks, pagination,
   null contract, role restrictions and offline/error states. Run flutter analyze
   and relevant tests; inspect rendered Android/iOS report/settings/overview screens
   and make incremental commits. Report actual evidence and remaining device checks.

This patch does not create a new API contract. Use the configured environment base
URL; production target is https://mhp.glsltd.co.uk/api/v 1/mobile after deployment.
Do not claim a production preference or mobile build changed until verified.
