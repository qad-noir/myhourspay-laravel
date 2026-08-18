# myhourspay production patch

This archive contains the authentication, email verification, notification, and landing-page files changed since production commit `ca60ed8`, plus the compiled Vite production assets.

## Deploy

1. Back up the production database and application files.
2. Extract the archive into the Laravel project root, preserving its directories.
3. Add or update these production environment values as needed:

```dotenv
APP_ENV=production
APP_DEBUG=false

SITE_DOMAIN=myhourspay.com
SITE_URL=https://myhourspay.com
SITE_LOGO_URL=/brand-logo.png
SITE_LOGO_MARK_URL=/brand-mark.png
SITE_EMAIL_DOMAIN=myhourspay.com
SITE_EMAIL=support@myhourspay.com
SITE_PHONE=
SITE_ADDRESS=
SITE_SOCIAL_X=
SITE_SOCIAL_FACEBOOK=
SITE_SOCIAL_LINKEDIN=

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=no-reply@myhourspay.com
MAIL_FROM_NAME=myhourspay
```

4. Confirm the PHP 8.4 CLI has the `dom` and `phar` extensions enabled. Laravel's console renderer and dependency tooling require them on this host.

5. Run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The migration creates the email verification code table. Existing users are marked verified during migration so they can continue to their workspace; newly registered users must enter the emailed six-digit code first.

The archive already includes compiled files under `public/build`, so Node.js is not required on the production server for this patch.
