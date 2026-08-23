<?php

namespace App\Providers;

use App\Models\HoursEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Observers\HoursEntryObserver;
use App\Observers\UserObserver;
use App\Observers\WorkspaceObserver;
use App\Policies\HoursEntryPolicy;
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
        Route::bind('hoursEntry', function (string $value): HoursEntry {
            $user = request()->user();

            return $user?->is_admin
                ? HoursEntry::query()->findOrFail($value)
                : $user?->hoursEntries()->findOrFail($value);
        });
    }
}
