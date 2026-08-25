<?php

namespace App\Policies;

use App\Models\HoursEntry;
use App\Models\User;

class HoursEntryPolicy
{
    public function view(User $user, HoursEntry $entry): bool
    {
        return $user->is($entry->user) && (int) $user->current_workspace_id === (int) $entry->workspace_id;
    }

    public function update(User $user, HoursEntry $entry): bool
    {
        return $this->view($user, $entry) && ! $this->locked($entry);
    }

    public function delete(User $user, HoursEntry $entry): bool
    {
        return $this->view($user, $entry) && ! $this->locked($entry);
    }

    private function locked(HoursEntry $entry): bool
    {
        return $entry->timesheet_id !== null && ($entry->relationLoaded('timesheet') ? $entry->timesheet?->isLocked() : $entry->timesheet()->whereIn('status', ['approved', 'locked'])->exists());
    }
}
