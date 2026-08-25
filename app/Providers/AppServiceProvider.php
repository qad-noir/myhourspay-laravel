<?php

namespace App\Providers;

use App\Models\HoursEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Observers\HoursEntryObserver;
use App\Observers\UserObserver;
use App\Observers\WorkspaceObserver;
use App\Policies\HoursEntryPolicy;
use App\Services\FeatureAccess;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(HoursEntry::class, HoursEntryPolicy::class);
        Blade::if('feature', fn (string $feature, ?Workspace $workspace = null): bool => auth()->check() && app(FeatureAccess::class)->allows(auth()->user(), $feature, $workspace));
        Route::bind('hoursEntry', function (string $value): HoursEntry {
            $user = request()->user();

            return $user?->is_admin
                ? HoursEntry::query()->findOrFail($value)
                : $user?->hoursEntries()->findOrFail($value);
        });
    }
}
