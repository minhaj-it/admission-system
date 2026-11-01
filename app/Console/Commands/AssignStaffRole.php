<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AssignStaffRole extends Command
{
    protected $signature = 'admission:assign-role {email} {role : Role slug, e.g. super-admin}';

    protected $description = 'Give an existing staff account a role (use once for accounts created before roles existed)';

    public function handle(): int
    {
        $user = User::where('email', Str::lower(trim($this->argument('email'))))->where('account_type', User::TYPE_STAFF)->first();
        $role = Role::where('slug', $this->argument('role'))->first();

        if (! $user || ! $role) {
            $this->error($user ? 'Unknown role.' : 'No staff account with that e-mail.');

            return self::FAILURE;
        }

        $old = ['role' => $user->role?->slug];
        $user->role_id = $role->id;
        $user->save();

        AuditLog::record('user.role_assigned', $user, $old, ['role' => $role->slug, 'source' => 'console']);

        $this->info("{$user->email} is now {$role->name}.");

        return self::SUCCESS;
    }
}
