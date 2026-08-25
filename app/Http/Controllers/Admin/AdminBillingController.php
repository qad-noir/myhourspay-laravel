<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingWebhookEvent;
use App\Models\EntitlementGrant;
use App\Models\Feature;
use App\Models\FeatureUsageDaily;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\BillingSettings;
use App\Services\FeatureAccess;
use App\Services\MonetizationManager;
use App\Services\OperationalIncidentRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Cashier\Subscription;
use Throwable;

class AdminBillingController extends Controller
{
    public function overview(BillingSettings $settings): View
    {
        $metrics = Cache::remember('admin:monetization:overview', now()->addMinutes(5), fn (): array => [
            'active_subscribers' => Subscription::query()->whereIn('stripe_status', ['active', 'trialing', 'past_due'])->count(),
            'trials' => Subscription::query()->where('stripe_status', 'trialing')->count(),
            'past_due' => Subscription::query()->where('stripe_status', 'past_due')->count(),
            'active_grants' => EntitlementGrant::query()->active()->count(),
            'expiring_grants' => EntitlementGrant::query()->active()->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(14))->count(),
            'webhook_failures' => BillingWebhookEvent::query()->where('status', 'failed')->count(),
            'feature_uses' => FeatureUsageDaily::query()->where('usage_date', '>=', today()->subDays(30))->sum('usage_count'),
            'trial_conversions' => Subscription::query()->where('stripe_status', 'active')->whereNotNull('trial_ends_at')->where('created_at', '>=', now()->subDays(30))->count(),
            'churn_30d' => Subscription::query()->whereNotNull('ends_at')->where('ends_at', '>=', now()->subDays(30))->count(),
            'active_seats' => DB::table('workspace_user')->join('users', 'users.id', '=', 'workspace_user.user_id')->whereNull('users.deleted_at')->whereNull('users.suspended_at')->distinct()->count('users.id'),
        ]);

        $recentWebhooks = BillingWebhookEvent::query()->latest()->limit(8)->get();
        $expiringGrants = EntitlementGrant::query()->active()->whereNotNull('expires_at')->with(['user', 'plan', 'feature'])->orderBy('expires_at')->limit(8)->get();
        $planDistribution = DB::table('subscription_items')->join('plan_prices', 'plan_prices.stripe_price_id', '=', 'subscription_items.stripe_price')->join('plans', 'plans.id', '=', 'plan_prices.plan_id')->where('plan_prices.kind', 'base')->select('plans.name', DB::raw('COUNT(DISTINCT subscription_items.subscription_id) as subscribers'))->groupBy('plans.id', 'plans.name')->orderByDesc('subscribers')->get();
        $topFeatures = FeatureUsageDaily::query()->select('feature_key', DB::raw('SUM(usage_count) as uses'))->where('usage_date', '>=', today()->subDays(30))->groupBy('feature_key')->orderByDesc('uses')->limit(8)->get();

        return view('admin.billing.overview', [
            'metrics' => $metrics,
            'recentWebhooks' => $recentWebhooks,
            'expiringGrants' => $expiringGrants,
            'checkoutEnabled' => $settings->boolean('checkout_enabled'),
            'enforcementEnabled' => $settings->boolean('paid_enforcement_enabled'),
            'launchDate' => $settings->get('monetization_launched_at'),
            'planDistribution' => $planDistribution,
            'topFeatures' => $topFeatures,
        ]);
    }

    public function updateSwitch(Request $request, MonetizationManager $manager, AdminAudit $audit): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', Rule::in(['checkout_enabled', 'paid_enforcement_enabled'])],
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirmed' => ['accepted'],
        ]);
        $setting = PlatformSetting::query()->where('key', 'billing.'.$validated['key'])->firstOrFail();
        $before = ['value' => $setting->value];
        $launchGrants = $manager->setSwitch($validated['key'], (bool) $validated['enabled'], $request->user());
        $setting->refresh();
        $audit->record($request, 'billing.switch_updated', $setting, $before, ['value' => $setting->value, 'launch_grants' => $launchGrants], $validated['reason']);
        Cache::forget('admin:monetization:overview');

        return back()->with('status', 'Billing control updated.'.($launchGrants ? " {$launchGrants} launch grants were created." : ''));
    }

    public function features(): View
    {
        $features = Feature::query()->with(['plans' => fn ($query) => $query->orderBy('tier')])->orderBy('category')->orderBy('name')->get()->groupBy('category');

        return view('admin.billing.features', compact('features'));
    }

    public function updateFeature(Request $request, Feature $feature, BillingSettings $settings, AdminAudit $audit): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['free', 'premium', 'disabled'])],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirmed' => ['accepted'],
        ]);
        if ($feature->locked && $validated['mode'] !== 'free') {
            return back()->withErrors(['feature' => 'This core feature is locked as always free.']);
        }

        $before = $feature->only(['mode']);
        $feature->update(['mode' => $validated['mode']]);
        $settings->touchEntitlements($request->user());
        $audit->record($request, 'billing.feature_mode_updated', $feature, $before, $feature->only(['mode']), $validated['reason']);
        Cache::forget('admin:monetization:overview');

        return back()->with('status', "{$feature->name} is now {$feature->mode}.");
    }

    public function plans(): View
    {
        $plans = Plan::query()->with(['prices', 'features'])->orderBy('tier')->get();
        $features = Feature::query()->orderBy('category')->orderBy('name')->get();

        return view('admin.billing.plans', compact('plans', 'features'));
    }

    public function updatePlanFeature(Request $request, Plan $plan, Feature $feature, BillingSettings $settings, AdminAudit $audit): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'quota' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'unlimited' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirmed' => ['accepted'],
        ]);
        $before = $plan->features()->whereKey($feature->id)->first()?->pivot?->value;
        $value = $feature->value_type === 'boolean'
            ? $request->boolean('enabled')
            : ($request->boolean('unlimited') ? null : (int) ($validated['quota'] ?? 0));

        $plan->features()->syncWithoutDetaching([$feature->id => ['value' => json_encode($value)]]);
        $settings->touchEntitlements($request->user());
        $audit->record($request, 'billing.plan_feature_updated', $plan, ['feature' => $feature->key, 'value' => $before], ['feature' => $feature->key, 'value' => $value], $validated['reason']);

        return back()->with('status', "{$feature->name} was updated for {$plan->name}.");
    }

    public function subscribers(): View
    {
        return view('admin.billing.subscribers');
    }

    public function grants(): View
    {
        return view('admin.billing.grants');
    }

    public function createGrant(): View
    {
        return view('admin.billing.grant-create', [
            'plans' => Plan::query()->where('active', true)->orderBy('tier')->get(),
            'features' => Feature::query()->where('mode', '!=', 'disabled')->orderBy('category')->orderBy('name')->get(),
            'selectedUser' => old('user_id') ? User::query()->find(old('user_id')) : null,
        ]);
    }

    public function storeGrant(Request $request, FeatureAccess $access, AdminAudit $audit): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')],
            'grant_type' => ['required', Rule::in(['plan', 'feature'])],
            'plan_id' => ['nullable', Rule::exists('plans', 'id')],
            'feature_id' => ['nullable', Rule::exists('features', 'id')],
            'quota' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        if (($validated['grant_type'] === 'plan' && empty($validated['plan_id'])) || ($validated['grant_type'] === 'feature' && empty($validated['feature_id']))) {
            return back()->withInput()->withErrors(['grant_type' => 'Select the plan or feature to grant.']);
        }

        $feature = $validated['grant_type'] === 'feature' ? Feature::query()->findOrFail($validated['feature_id']) : null;
        $grant = EntitlementGrant::query()->create([
            'user_id' => $validated['user_id'],
            'plan_id' => $validated['grant_type'] === 'plan' ? $validated['plan_id'] : null,
            'feature_id' => $feature?->id,
            'value' => $feature?->value_type === 'quota' ? ($validated['quota'] ?? null) : null,
            'starts_at' => $validated['starts_at'] ?? now(),
            'expires_at' => $validated['expires_at'] ?? null,
            'reason' => $validated['reason'],
            'created_by' => $request->user()->id,
        ]);
        $user = User::query()->findOrFail($validated['user_id']);
        $access->invalidate($user);
        $audit->record($request, 'billing.grant_created', $grant, [], $grant->only(['public_id', 'user_id', 'plan_id', 'feature_id', 'value', 'starts_at', 'expires_at']), $validated['reason']);
        Cache::forget('admin:monetization:overview');

        return redirect()->route('admin.billing.grants')->with('status', 'Entitlement grant created.');
    }

    public function revokeGrant(Request $request, EntitlementGrant $grant, FeatureAccess $access, AdminAudit $audit): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        if ($grant->revoked_at) {
            return back()->withErrors(['grant' => 'This grant has already been revoked.']);
        }

        $grant->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id, 'revocation_reason' => $validated['reason']]);
        $access->invalidate($grant->user);
        $audit->record($request, 'billing.grant_revoked', $grant, ['revoked_at' => null], ['revoked_at' => $grant->revoked_at], $validated['reason']);
        Cache::forget('admin:monetization:overview');

        return back()->with('status', 'Grant revoked.');
    }

    public function health(): View
    {
        return view('admin.billing.health', [
            'priceHealth' => Plan::query()->where('purchasable', true)->with('prices')->get(),
            'stripeConfigured' => filled(config('cashier.key')) && filled(config('cashier.secret')) && filled(config('cashier.webhook.secret')),
        ]);
    }

    public function resync(Request $request, User $user, FeatureAccess $access, AdminAudit $audit, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        $subscription = $user->subscription('default');
        if (! $subscription) {
            return back()->withErrors(['subscription' => 'This user has no local subscription to resync.']);
        }

        try {
            $before = ['status' => $subscription->stripe_status];
            $subscription->syncStripeStatus();
            $access->invalidate($user);
            $audit->record($request, 'billing.subscription_resynced', $user, $before, ['status' => $subscription->fresh()->stripe_status], 'Manual Stripe reconciliation');

            return back()->with('status', 'Stripe subscription state resynced.');
        } catch (Throwable $exception) {
            Log::error('Manual Stripe reconciliation failed.', ['user_id' => $user->id, 'exception' => $exception]);
            $reference = $incidents->record('billing.reconciliation_failed', $exception, [
                'severity' => 'critical',
                'name' => $user->name,
                'email' => $user->email,
                'exception_message' => 'Manual Stripe subscription reconciliation failed.',
            ])->reference;

            return back()->withErrors(['subscription' => 'Stripe resync failed. Reference: '.$reference]);
        }
    }

    public function cancelSubscriber(Request $request, User $user, FeatureAccess $access, AdminAudit $audit, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirmed' => ['accepted'],
        ]);
        $subscription = $user->subscription('default');
        if (! $subscription || ! $subscription->valid()) {
            return back()->withErrors(['subscription' => 'This user has no active subscription to cancel.']);
        }

        try {
            $before = ['status' => $subscription->stripe_status, 'ends_at' => $subscription->ends_at];
            $subscription->cancel();
            $access->invalidate($user);
            $audit->record($request, 'billing.subscription_cancellation_scheduled', $user, $before, ['ends_at' => $subscription->fresh()->ends_at], $validated['reason']);

            return back()->with('status', 'Cancellation scheduled for the end of the paid period.');
        } catch (Throwable $exception) {
            Log::error('Administrative subscription cancellation failed.', ['user_id' => $user->id, 'exception' => $exception]);
            $reference = $incidents->record('billing.admin_cancellation_failed', $exception, [
                'severity' => 'error',
                'name' => $user->name,
                'email' => $user->email,
                'exception_message' => 'Administrative Stripe cancellation failed.',
            ])->reference;

            return back()->withErrors(['subscription' => 'Cancellation could not be scheduled. Reference: '.$reference]);
        }
    }
}
