<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use LogicException;

class CurrentWorkspace
{
    public function existsFor(User $user): bool
    {
        return $this->query($user)->exists();
    }

    public function for(User $user): Workspace
    {
        return $this->query($user)->first() ?? throw new LogicException('The user does not have a valid current workspace.');
    }

    private function query(User $user)
    {
        return $user->workspaces()->whereKey($user->current_workspace_id);
    }
}
