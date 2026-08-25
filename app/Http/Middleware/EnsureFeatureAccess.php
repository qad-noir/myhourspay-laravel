<?php

namespace App\Http\Middleware;

use App\Services\CurrentWorkspace;
use App\Services\FeatureAccess;
use App\Services\FeatureUsage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureAccess
{
    public function __construct(private readonly FeatureAccess $features, private readonly CurrentWorkspace $workspaces, private readonly FeatureUsage $usage) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $workspace = $request->user()?->current_workspace_id ? $this->workspaces->for($request->user()) : null;
        if ($request->user() && $this->features->allows($request->user(), $feature, $workspace)) {
            $this->usage->record($request->user(), $feature, $workspace);

            return $next($request);
        }

        if ($request->expectsJson()) {
            $requiredPlan = $this->features->requiredPlan($feature);

            return response()->json([
                'message' => 'This feature is not available on your current plan.',
                'code' => 'feature_not_available',
                'feature' => $feature,
                'required_plan' => $requiredPlan?->key,
                'upgrade_url' => route('billing.index'),
            ], 403);
        }

        return redirect()->route('billing.index')->withErrors(['plan' => 'This feature requires a different plan.']);
    }
}
