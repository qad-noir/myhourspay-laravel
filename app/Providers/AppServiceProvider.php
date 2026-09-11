<?php

namespace App\Providers;

use App\Http\Controllers\StripeWebhookController;
use App\Models\HoursEntry;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Observers\HoursEntryObserver;
use App\Observers\UserObserver;
use App\Observers\WorkspaceObserver;
use App\Policies\HoursEntryPolicy;
use App\Services\FeatureAccess;
use App\Services\StripeSubscriptionSync;
use App\Services\SubscriptionState;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WebhookController::class, StripeWebhookController::class);
        $this->app->scoped(SubscriptionState::class);
        $this->app->scoped(StripeSubscriptionSync::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('marketing-unsubscribe', fn (Request $request) => Limit::perMinute(30)->by(hash('sha256', (string) $request->route('token'))));
        // Let the application incident handler handle errors, including in debug mode.
        config(['datatables.error' => 'throw']);
        HoursEntry::observe(HoursEntryObserver::class);
        Workspace::observe(WorkspaceObserver::class);
        User::observe(UserObserver::class);
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::calculateTaxes();
        Cashier::keepPastDueSubscriptionsActive();
        Gate::policy(HoursEntry::class, HoursEntryPolicy::class);
        Blade::if('feature', fn (string $feature, ?Workspace $workspace = null): bool => auth()->check() && app(FeatureAccess::class)->allows(auth()->user(), $feature, $workspace));
        Route::bind('hoursEntry', function (string $value): HoursEntry {
            $user = request()->user();

            return $user?->is_admin
                ? HoursEntry::query()->findOrFail($value)
                : $user?->hoursEntries()->findOrFail($value);
        });
        RateLimiter::for('premium-api', function (Request $request): Limit {
            $workspace = $request->route('workspace');
            $limit = $request->user() ? app(FeatureAccess::class)->value($request->user(), 'api_access', $workspace instanceof Workspace ? $workspace : null) : 60;

            return Limit::perMinute(max(1, min(5000, is_numeric($limit) ? (int) $limit : 120)))->by($request->user()?->id ?: $request->ip());
        });
    }
}
