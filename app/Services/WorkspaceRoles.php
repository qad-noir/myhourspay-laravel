<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;

class WorkspaceRoles
{
    public function role(User $user, Workspace $workspace): ?string
    {
        if ((int) $workspace->owner_id === (int) $user->id) {
            return 'owner';
        }

        return $workspace->users()->whereKey($user->id)->value('workspace_user.role');
    }

    public function canManage(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), ['owner', 'administrator'], true);
    }

    public function canReview(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), ['owner', 'administrator', 'manager'], true);
    }

    public function canRunPayroll(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), ['owner', 'administrator', 'payroll'], true);
    }
}
