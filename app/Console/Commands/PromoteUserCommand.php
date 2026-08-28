<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Bootstrap command: promote a user to super admin with full access. Needed to
 * create the first admin before the in-app user-management screen exists (and as
 * a break-glass afterwards). Run it from Laravel Cloud's command runner, e.g.
 * `php artisan user:promote a.pappas@yoltlabs.com`.
 */
class PromoteUserCommand extends Command
{
    protected $signature = 'user:promote {email : The email of the user to promote}';

    protected $description = 'Promote a user to super admin with full Finance and Business access';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $user->forceFill([
            'is_admin' => true,
            'can_access_finance' => true,
            'can_access_crm' => true,
            'is_active' => true,
        ])->save();

        $this->info("[{$user->email}] is now a super admin with full access.");

        return self::SUCCESS;
    }
}
