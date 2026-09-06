# Admin controls and capability usage patch

Apply this incremental patch to the application after the billing lifecycle and payroll currency patches. Copy the ZIP contents into the application root, preserving paths. Compiled production assets are included; no migration or dependency installation is needed.

After copying, run:

```sh
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Keep older hashed assets until existing browser sessions have refreshed. Open the grant creation page, search for a user, and verify the green Selected indicator and dropdown arrow. Check keyboard navigation, changing the selected user, and narrow mobile widths. The monetisation overview links to `/admin/billing/capabilities`, with all capabilities ranked by usage over the same 30-day window, including zero-use entries. The overview shows at most ten entries.

## Reusing the controls

`resources/css/select-controls.css`, imported once by `resources/js/app.js`, provides arrow defaults for all existing and future native single-select elements, including dynamically rendered fields. No per-page arrow markup is needed. Multiple selects and visible listboxes keep their native controls. Forced-colour mode uses the native select arrow.

Use the existing `data-admin-user-select` and `data-admin-workspace-select` attributes for remote user/workspace controls. Their shared Tom Select renderer includes the explicit Selected badge; `has-items` controls the green selection border and background, so clearing a selection returns to the empty state. Continue supplying a visible label and the appropriate required/disabled attributes.

## Validation

Admin billing regression tests passed (9 tests, 53 assertions). Blade compilation passed. Automated browser access returned Transport closed, so desktop/mobile visual confirmation remains a deployment smoke check.
