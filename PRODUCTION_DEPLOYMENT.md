# myhourspay production update

This incremental update is intended for installations already running the
`myhourspay-premium-platform-25-08-2026-15-13-50.zip` release (commit
`7505e71`). It contains the admin navigation and DataTable restoration fixes,
polished Pro/Business controls, calendar-provider incident handling, and safe
administrative plan-price versioning.

## Deploy

1. Back up the application files and database.
2. Extract the ZIP into the Laravel application root, preserving its folders.
3. Keep the production `.env` file; this patch does not replace it.
4. Run:

   ```bash
   /opt/alt/php84/usr/bin/php artisan migrate --force
   /opt/alt/php84/usr/bin/php artisan optimize:clear
   /opt/alt/php84/usr/bin/php artisan config:cache
   /opt/alt/php84/usr/bin/php artisan route:cache
   /opt/alt/php84/usr/bin/php artisan view:cache
   ```

5. Confirm the web server can read `public/build/manifest.json` and the three
   fingerprinted assets included in this archive.

No production Node.js build is required; compiled Vite assets are included.
The package metadata is included for the next full dependency deployment.

## Database change

`2026_08_25_160000_version_plan_prices_for_admin_updates.php` removes the
single-row catalogue constraint and replaces it with a normal compact index.
Price changes now create a new active row and retain previous Stripe Price IDs
for existing subscriptions.

Do not roll this migration back after administrators have versioned prices:
the retired catalogue rows are subscription history and must be retained.

## Post-deployment checks

- Open an admin DataTable, view a record, return with browser history, and
  confirm the search, pagination, actions, and responsive layout are restored.
- Confirm Pro reminder switches and Business leave/payroll/webhook controls
  retain a visible checked state.
- Open **Admin → Monetisation → Plans & limits** and confirm current plan and
  seat prices appear in versioning cards.
- Keep checkout disabled until all active Pro/Business monthly, yearly, and
  Business additional-seat prices have verified Stripe Price IDs.
- Review **Admin → Incidents** for any new deployment or Stripe-validation
  reference.

## Files in this incremental update

- `app/Http/Controllers/Admin/AdminBillingController.php`
- `app/Http/Controllers/Admin/AdminController.php`
- `app/Http/Controllers/CalendarIntegrationController.php`
- `app/Http/Controllers/ProController.php`
- `app/Models/PlanPrice.php`
- `app/Services/BusinessSeatBilling.php`
- `app/Services/CalendarIntegrationService.php`
- `app/Services/MonetizationManager.php`
- `app/Services/PlanPriceVersioner.php`
- `database/migrations/2026_08_25_160000_version_plan_prices_for_admin_updates.php`
- `package.json`
- `package-lock.json`
- `resources/css/admin-price-controls.css`
- `resources/css/app.css`
- `resources/css/pro-business-controls.css`
- `resources/js/app.js`
- `resources/views/admin/audit-logs/show.blade.php`
- `resources/views/admin/billing/plans.blade.php`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/hours/form.blade.php`
- `resources/views/admin/incidents/show.blade.php`
- `resources/views/admin/partials/user-actions.blade.php`
- `resources/views/admin/users/create.blade.php`
- `resources/views/admin/users/show.blade.php`
- `resources/views/admin/workspaces/create.blade.php`
- `resources/views/admin/workspaces/show.blade.php`
- `resources/views/business/index.blade.php`
- `resources/views/components/dashboard/sidebar.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/pro/index.blade.php`
- `routes/web.php`
- compiled files under `public/build/`

Test files are intentionally excluded from the production ZIP.
