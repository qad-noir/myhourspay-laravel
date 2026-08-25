<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceActivityLog;
use Illuminate\Database\Eloquent\Model;

class WorkspaceActivity
{
    public function record(Workspace $workspace, ?User $actor, string $action, ?Model $target = null, array $metadata = []): WorkspaceActivityLog
    {
        return WorkspaceActivityLog::query()->create(['workspace_id' => $workspace->id, 'actor_id' => $actor?->id, 'action' => $action, 'target_type' => $target ? $target::class : null, 'target_id' => $target?->getKey(), 'metadata' => $metadata, 'ip_address' => request()->ip(), 'occurred_at' => now()]);
    }
}
