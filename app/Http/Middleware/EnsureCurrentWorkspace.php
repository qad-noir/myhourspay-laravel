<?php

namespace App\Http\Middleware;

use App\Services\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentWorkspace
{
    public function __construct(private readonly CurrentWorkspace $workspaces) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        if ($request->user()->workspace_onboarding_reset_at || ! $this->workspaces->existsFor($request->user())) {
            return to_route('workspaces.onboarding');
        }

        return $next($request);
    }
}
