# Compact table theme and public pricing page

Apply this incremental patch after the beta/table patch. Copy the ZIP contents into the application root while preserving paths, then run:

```sh
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

No migration or Stripe change is required. Rebuild caches after copying the compiled assets. Keep previous hashed assets available during the browser refresh window.

## What changed

- Compact server-side DataTables now inherit the existing admin DataTables theme: ink text, orange focus/action states, violet sort indicators, compact row height, project fonts and responsive paging/search controls. Numeric time and earnings values use the ledger monospace face.
- `/pricing` is public and reads active plan prices, intervals, tax-inclusion flags, business included seats, additional-seat prices and catalogue features from the local database. It supports monthly/yearly URLs through `?interval=monthly|yearly`, uses the current checkout switch, and labels beta access without exposing operational setting names.
- The public header links Privacy to `/policy` and Pricing to `/pricing`; footer privacy, terms, pricing, support and resource links now resolve to real destinations.

## Smoke checks

Open `/pricing` while signed out and verify Free, Pro and Business cards, monthly/yearly switching, feature comparison, beta copy and mobile stacking. If checkout is disabled, paid cards explain that subscriptions are opening soon. Open a report or admin history page and verify search, page length, sorting, compact rows, horizontal scrolling and retry behavior.

Pricing reads catalogue values. If a plan price or Stripe Price ID is missing, the page identifies that interval as unavailable; it does not invent a price. Authenticated users are sent to Plans & billing to manage a subscription.

## Rollback

Restore the files in the manifest and matching previous Vite manifest/assets, then clear and rebuild Laravel caches. There is no database rollback step.

Validation for this patch: 187 tests, 180 passed, 7 skipped, 990 assertions; Blade cache compilation passed; production Vite build passed with the existing large-JavaScript-chunk warning. Automated browser inspection was unavailable because the browser transport returned `Transport closed`, so perform the visual smoke checks above after deployment.
