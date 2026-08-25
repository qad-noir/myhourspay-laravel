<?php

namespace App\Http\Middleware;

use App\Services\CurrentWorkspace;
use App\Services\WorkspaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceIsWritable
{
    public function __construct(private readonly CurrentWorkspace $current, private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $this->current->for($request->user());
        if ($this->access->isWritable($request->user(), $workspace)) {
            return $next($request);
        }

        $message = 'This workspace is preserved as read-only on your current plan. Switch to your primary workspace or upgrade to make changes.';
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code' => 'workspace_read_only',
                'upgrade_url' => route('billing.index'),
            ], 403);
        }

        return redirect()->route('billing.index')->withErrors(['plan' => $message]);
    }
}
