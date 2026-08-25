<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;

class WorkspaceAccess
{
    public function __construct(private readonly FeatureAccess $features) {}

    public function canCreateOwnedWorkspace(User $user): bool
    {
        if ($user->workspace_onboarding_reset_at || ! $user->ownedWorkspaces()->exists()) {
            return true;
        }

        $limit = $this->features->value($user, 'workspace_limit');

        return $limit === null || $user->ownedWorkspaces()->count() < (int) $limit;
    }

    public function isWritable(User $user, Workspace $workspace): bool
    {
        if ((int) $workspace->owner_id !== (int) $user->id) {
            return $workspace->users()->whereKey($user->id)->wherePivotIn('role', ['owner', 'administrator', 'manager', 'member'])->exists()
                && $this->features->allows($user, 'team_members', $workspace);
        }

        $limit = $this->features->value($user, 'workspace_limit');
        if ($limit === null) {
            return true;
        }

        return $user->ownedWorkspaces()->orderBy('created_at')->orderBy('id')->limit((int) $limit)->pluck('id')->contains($workspace->id);
    }
}
