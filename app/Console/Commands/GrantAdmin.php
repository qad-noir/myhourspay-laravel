<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantAdmin extends Command
{
    protected $signature = 'admin:grant {email : Email address of the existing user}';

    protected $description = 'Grant platform administrator access to an existing user';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user exists with that email address.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info("Admin access granted to {$user->email}.");

        return self::SUCCESS;
    }
}
