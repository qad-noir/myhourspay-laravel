<?php

namespace App\Observers;

use App\Models\Workspace;
use App\Services\ScaleCache;

class WorkspaceObserver
{
    public function __construct(private readonly ScaleCache $cache) {}

    public function saved(Workspace $workspace): void
    {
        if ($workspace->wasRecentlyCreated || $workspace->wasChanged(['weekly_target_minutes', 'default_break_minutes', 'default_break_type'])) {
            $this->cache->forgetWorkspace($workspace);
        }
    }

    public function deleted(Workspace $workspace): void
    {
        $this->cache->forgetWorkspace($workspace);
    }

    public function restored(Workspace $workspace): void
    {
        $this->cache->forgetWorkspace($workspace);
    }

    public function forceDeleted(Workspace $workspace): void
    {
        $this->cache->forgetWorkspace($workspace);
    }
}
