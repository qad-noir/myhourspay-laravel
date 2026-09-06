# Public FAQ and pricing FAQ patch

This is an incremental patch for the existing public pricing and navigation rollout. It adds `/faq` and a reusable Tailwind-first FAQ component, then uses the same accordion at the bottom of `/pricing`.

## Deployment

1. Copy the files listed in `PRODUCTION_PATCH_MANIFEST_FAQ.txt`, including the compiled assets under `public/build`.
2. Run `php artisan optimize:clear`.
3. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
4. Smoke test `/faq` and `/pricing`: the first question is open, selecting another question closes the previous one, and the support action opens the configured support email.
5. Check the desktop two-column layout and the stacked mobile layout. Keyboard focus should remain visible on summaries and the support action.

The FAQ uses native `details` elements with a small reusable JavaScript initializer. No database migration, Stripe mutation, subscription change, or Livewire request is required.

## Rollback

Restore the previous public navigation, pricing view, app bundle, manifest, and compiled asset files, then repeat the cache commands. The `/faq` route may then be removed if the release is being rolled back completely.

## Validation

The full application suite passed with 190 tests, 1,018 assertions, and seven skips. The production Vite build passed with the existing large JavaScript chunk warning. Authenticated browser visual verification was unavailable because the desktop browser transport was closed; run the smoke checks above in the target environment.
