# FAQ visual correction — 7 September 2026

Apply this incremental patch after the public FAQ rollout. Source commit: `6b23d9d`.

## Changes

- Shared `/faq` and `/pricing` FAQ: equal desktop columns, white section, compact neutral accordion cards, 38px desktop / 30px mobile headings, lighter question text, aligned support card and consistent SVG arrows.
- Named Tailwind `font-heading` and `font-body` utilities retain Manrope and DM Sans with system sans-serif fallbacks.
- Corrected the font requests: the previous repeated `family` query parameter loaded DM Sans without loading Manrope.
- First answer starts open; native exclusive details groups also work without JavaScript. Existing FAQ JavaScript remains as a compatibility enhancement.
- Mobile order is introduction, questions, then support. This is the assumed mobile arrangement because the reference only shows desktop.
- Privacy and terms links are available inside the pricing FAQ answer.

## Deploy

1. Back up the files in `PRODUCTION_PATCH_MANIFEST_FAQ_REFINEMENT.txt` and the existing build manifest/assets.
2. Upload the listed source files and compiled `public/build` assets. The Tailwind config is needed for future builds; no npm install is needed on the host.
3. Run `php artisan view:clear` then `php artisan view:cache`.
4. Visit `/faq` and `/pricing`, verify the fonts, and switch between questions. Check the pricing monthly/yearly toggle too.

No route, database or subscription changes. Restore backed-up files and clear the view cache to roll back. Keep previous hashed assets available during rollout for cached pages.

## Verification

- Full application suite: 190 tests, 183 passed, 7 skipped, 1,018 assertions.
- Blade compilation and production build passed. Vite retains the existing warning about the main JavaScript chunk exceeding 500 kB.
- Local Playwright Chromium checks and screenshots: `/faq` and `/pricing` at 1457, 1024, 390 and 320 CSS pixels. Checks cover real Manrope loading, no horizontal overflow, first-open state, click switching, Enter/Space controls, reload, and pricing interval changes.
- Exploratory checks passed with JavaScript disabled and font requests blocked, including exclusive accordion switching and no horizontal overflow. Browser contexts close after each check.
- The requested persistent `js_repl` tool was unavailable and the CUA connector returned `Transport closed`. Verification used the same Playwright browser automation through a local script instead.
- Evidence and the reproducible QA script live in `storage/app/faq-qa/`; screenshots are delivered separately from this deployable patch.
