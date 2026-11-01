<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class MakeStaffUser extends Command
{
    protected $signature = 'admission:make-staff {email} {--name=} {--username=} {--mobile=} {--role=super-admin : Role slug (super-admin, admin, admission-officer, accountant, viewer or a custom one)}';

    protected $description = 'Create an active staff account (admin panel access) from the command line';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $username = $this->option('username') ?: Str::before($email, '@');
        $name = $this->option('name') ?: $username;
        $mobile = User::normalizeMobile($this->option('mobile')) ?: null;

        $role = Role::where('slug', $this->option('role'))->where('is_active', true)->first();

        if (! $role) {
            $this->error('Unknown or inactive role: ' . $this->option('role'));

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password');
        $confirm = (string) $this->secret('Confirm password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            compact('email', 'username', 'mobile', 'password'),
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'username' => ['required', 'regex:/^[A-Za-z0-9._]{3,20}$/', 'unique:users,username'],
                'mobile' => ['nullable', 'regex:/^01[3-9]\d{8}$/', 'unique:users,mobile'],
                'password' => ['required', Password::defaults()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User(compact('name', 'username', 'email', 'mobile', 'password'));
        $user->account_type = User::TYPE_STAFF;
        $user->status = User::STATUS_ACTIVE;
        $user->role_id = $role->id;
        $user->email_verified_at = now();
        $user->save();

        AuditLog::record('user.created', $user, null, ['username' => $username, 'email' => $email, 'role' => $role->slug, 'source' => 'console']);

        $this->info("Staff account created: {$user->email} ({$role->name})");

        return self::SUCCESS;
    }
}
