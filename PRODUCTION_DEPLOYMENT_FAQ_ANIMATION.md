# FAQ accordion animation — 7 September 2026

Incremental patch after the FAQ visual refinement. Source commit: `53f248e`.

Answers expand and collapse over 260ms with easing. Switching questions animates both panels together; repeated clicks reverse from the current height. Arrow rotation and colour transitions follow the requested state. Reduced-motion users receive immediate changes. Native details remain functional without JavaScript.

Deploy the files in `PRODUCTION_PATCH_MANIFEST_FAQ_ANIMATION.txt`, including all compiled assets and the build manifest. Run `php artisan view:clear` followed by `php artisan view:cache`. No migrations or billing changes.

Check `/faq` and the bottom of `/pricing`: open, close, switch questions, click rapidly, and use Enter/Space. Only one answer should remain open after transitions settle. Check reduced-motion mode too.

Validation: local Playwright Chromium passed on both pages at 1457px and 390px, covering simultaneous animations, intermediate heights, rapid clicks, keyboard operation, reduced motion, final screenshots and horizontal overflow. Seven relevant PHP tests passed (63 assertions); Blade compilation and production build passed. Existing Vite main-chunk size warning remains.

Rollback: restore the prior versions of the listed source and compiled files and clear the view cache. Keep prior hashed assets during rollout for cached pages.
