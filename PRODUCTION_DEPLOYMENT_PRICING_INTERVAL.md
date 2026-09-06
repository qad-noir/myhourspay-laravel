# Pricing interval switch patch

This is an incremental patch for the existing pricing page and compact-table rollout. It replaces the public pricing page’s interval links with an in-page Month/Year switch; selecting an interval updates the visible plan prices without a request or full-page refresh.

## Files

Apply the files listed in `PRODUCTION_PATCH_MANIFEST_PRICING_INTERVAL.txt`, including the compiled assets under `public/build`. Do not publish the legal draft markdown files as part of this patch.

## Deployment

1. Copy the listed application files and compiled assets into the release.
2. Run `php artisan optimize:clear`.
3. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
4. Confirm `/pricing` loads with Monthly selected, then select Yearly and Monthly again. The visible prices, billing labels, and business seat price should change without a document reload.
5. Confirm keyboard focus and `aria-pressed` state move with the selected interval.

No database migration, Stripe mutation, subscription change, or URL query parameter is required. The existing `?interval=monthly|yearly` URLs remain readable as initial-state deep links.

## Rollback

Restore the previous pricing Blade, public pricing stylesheet, app bundle, manifest, and compiled asset files, then repeat the cache commands.

## Validation

The pricing feature tests pass with five tests and 44 assertions. The full application suite previously passed with 187 tests, 990 assertions, and seven skips. The production Vite build passed; it retains the existing large JavaScript chunk warning. Authenticated browser visual verification was unavailable because the desktop browser transport was closed, so run the smoke checks above in the target environment.
