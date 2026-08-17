<?php

namespace App\Policies;

use App\Models\HoursEntry;
use App\Models\User;

class HoursEntryPolicy
{
    public function view(User $user, HoursEntry $entry): bool
    {
        return $user->is($entry->user);
    }

    public function update(User $user, HoursEntry $entry): bool
    {
        return $user->is($entry->user);
    }

    public function delete(User $user, HoursEntry $entry): bool
    {
        return $user->is($entry->user);
    }
}
