# myhourspay production patch

This archive contains application files changed since commit `7a788e8`, plus the compiled Vite production assets.

## Deploy

1. Back up the production database and application files.
2. Extract the archive into the Laravel project root, preserving its directories.
3. Add or update these production environment values as needed:

```dotenv
SITE_DOMAIN=myhourspay.com
SITE_URL=https://myhourspay.com
SITE_EMAIL_DOMAIN=myhourspay.com
SITE_EMAIL=support@myhourspay.com
SITE_PHONE=
SITE_ADDRESS=
SITE_SOCIAL_X=
SITE_SOCIAL_FACEBOOK=
SITE_SOCIAL_LINKEDIN=
```

4. Run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The archive already includes compiled files under `public/build`, so Node.js is not required on the production server for this patch.
