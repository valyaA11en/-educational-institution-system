<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class EnforceTwoFactor extends Command
{
    protected $signature = '2fa:enforce';
    protected $description = 'Enforce 2FA for admin, methodist, and management roles';

    public function handle(): void
    {
        $enforceSetting = \App\Models\Setting::where('key', 'security.2fa.enforce_roles')
            ->value('value_json');

        $enforceRoles = $enforceSetting['roles'] ?? ['admin', 'methodist', 'management'];

        $this->info("Enforcing 2FA for roles: " . implode(', ', $enforceRoles));

        $roleIds = Role::whereIn('name', $enforceRoles)->pluck('id');
        
        $users = User::whereHas('roles', function ($query) use ($roleIds) {
            $query->whereIn('roles.id', $roleIds);
        })->get();

        $enforced = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $twoFactor = \App\Models\TwoFactorAuth::where('user_id', $user->id)
                ->where('enabled', true)
                ->first();

            if (!$twoFactor) {
                $this->warn("User {$user->email} ({$user->fio}) does not have 2FA enabled");
                $enforced++;
            } else {
                $skipped++;
            }
        }

        $this->info("Checked {$users->count()} users. {$enforced} need to enable 2FA, {$skipped} already have it enabled.");
    }
}


