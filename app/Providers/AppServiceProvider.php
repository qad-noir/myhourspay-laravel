<?php

namespace App\Providers;

use App\Models\HoursEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Observers\HoursEntryObserver;
use App\Observers\UserObserver;
use App\Observers\WorkspaceObserver;
use App\Policies\HoursEntryPolicy;
use App\Services\BillingWebhookTracker;
use App\Services\FeatureAccess;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        HoursEntry::observe(HoursEntryObserver::class);
        Workspace::observe(WorkspaceObserver::class);
        User::observe(UserObserver::class);
        Cashier::calculateTaxes();
        Cashier::keepPastDueSubscriptionsActive();
        Gate::policy(HoursEntry::class, HoursEntryPolicy::class);
        Blade::if('feature', fn (string $feature, ?Workspace $workspace = null): bool => auth()->check() && app(FeatureAccess::class)->allows(auth()->user(), $feature, $workspace));
        Event::listen(WebhookReceived::class, fn (WebhookReceived $event) => app(BillingWebhookTracker::class)->received($event->payload));
        Event::listen(WebhookHandled::class, fn (WebhookHandled $event) => app(BillingWebhookTracker::class)->handled($event->payload));
        Route::bind('hoursEntry', function (string $value): HoursEntry {
            $user = request()->user();

            return $user?->is_admin
                ? HoursEntry::query()->findOrFail($value)
                : $user?->hoursEntries()->findOrFail($value);
        });
    }
}
