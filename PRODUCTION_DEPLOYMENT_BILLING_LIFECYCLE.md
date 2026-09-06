# Billing lifecycle and payroll guidance patch

## Deploy

1. Copy the patch files into the application root, preserving their paths.
2. Run `php artisan migrate --force`.
3. Run `php artisan optimize:clear` and then `php artisan config:cache` when configuration is ready.
4. Confirm Stripe is using test or live keys consistently in the target environment.
5. Confirm the Stripe webhook endpoint is `https://your-domain.example/stripe/webhook`, has the configured `STRIPE_WEBHOOK_SECRET`, and includes subscription, invoice, checkout, and schedule events.
6. Run `php artisan billing:reconcile-stripe --dry-run` to inspect known Stripe customers. Run `php artisan billing:reconcile-stripe` to import missing local subscriptions. Use `--user=<id>` for one customer.
7. Run `php artisan schedule:work` or the production scheduler so hourly reconciliation continues.

## Billing recovery checks

- Open Plans & billing and confirm the recurring plan price, trial or renewal date, and invoice status.
- A trial can show a £0.00 invoice while still displaying its recurring price; this is expected.
- Confirm the admin billing overview separates paid subscribers, trials, and past-due accounts.
- Confirm Admin → Users shows each account’s plan or granted access and the subscription state.
- For a webhook outage, use the customer-scoped reconciliation command after correcting the endpoint or secret. Reconciliation is idempotent and reads current Stripe state before persisting it.

## Payroll checks

- Payroll profiles default to the latest approved or locked week in the current workspace.
- An unapproved profile shows the links Log hours and Review/View timesheets plus the approval sequence.
- `earnings_minor` remains an integer minor-unit value. For example, `20560` means £205.60.

The patch does not alter Stripe prices, create charges, change the hosted webhook destination, or include environment files.
