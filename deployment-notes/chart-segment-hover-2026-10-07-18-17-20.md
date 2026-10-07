# Separate chart segment hover details

Follow-up to 6d2a2ef. Green segments show only daily overtime amount and its
contracted-hours threshold. Orange segments show only regular hours and break
details. Both use the existing tooltip class, colours, font, spacing, shadow,
position and transition. Segment keyboard focus shows the same relevant details.
The existing day-level summary tooltip remains available outside the segments.
Calculations and recorded data are unchanged.

Back up destination files from the supplied manifest. In /home/raaingqv/mhp-app:

```sh
php84 artisan down
# Upload/extract runtime ZIP into this Laravel root, preserving directories.
# Include the supplied public/build manifest and assets together.
php84 artisan optimize:clear
php84 artisan config:cache
php84 artisan route:cache
php84 artisan view:cache
php84 artisan up
```

No migrations, dependencies or production npm build are needed. The CSS is built
locally. Preserve .env and APP_KEY and retain previous hashed assets for old tabs.
Use PHPRC=/home/raaingqv/mhp-app /opt/alt/php84/usr/bin/php if php84 is unavailable.
Restore backed-up files/manifest and rebuild caches for rollback.

Acceptance: hover green and orange portions of a split bar, then keyboard-focus
each. Only the relevant segment tooltip should appear. Check first/last weekday
positioning, regular-only and empty days, and movement between colours. A keyboard
focused segment must not cause two tooltips when the pointer hovers another colour.
Production deployment and browser/device acceptance have not been performed here.
The regression verifies isolated tooltip content and segment accessibility links.

Package: `chart-segment-hover-2026-10-07-18-17-20.zip`
Source base: `6d2a2ef`
Source HEAD: `d4b5f5332d45f6ca2f46f8817c75ab64c24a08e3`
Files: 6
SHA-256: `599adaf19083563c21c3853cd02d719da0e713c0d91132b50f18241e8bfa423f`

