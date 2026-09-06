<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrialChoice
{
    public function handle(Request $request, Closure $next): Response
    {
        $protected = $request->routeIs('dashboard', 'hours.*', 'pro.*', 'business.*', 'workspaces.*', 'settings.*', 'livewire.*') || $request->is('api/v1/*');
        if (! $protected || $request->routeIs('hours.reports.csv') || ! $request->user()
            || ! app(SubscriptionState::class)->needsTrialChoice($request->user())) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Your trial has ended. Choose a paid plan or continue on Free.', 'code' => 'trial_choice_required', 'upgrade_url' => route('billing.index')], 403);
        }

        return redirect()->route('billing.index');
    }
}
