<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * The only way to become an admin: run on the server. No web request can set `is_admin`.
 */
class MakeAdmin extends Command
{
    protected $signature = 'user:admin {email : Email of an existing user} {--revoke : Remove admin access instead of granting it}';

    protected $description = 'Give or remove admin access (the light admin page at /admin) for an existing user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user with that email. Nothing was changed.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_admin' => $grant])->save();

        $this->info($grant ? "{$user->email} is now an admin." : "{$user->email} is no longer an admin.");

        return self::SUCCESS;
    }
}
