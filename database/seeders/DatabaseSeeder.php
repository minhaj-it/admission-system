<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Fictional demo accounts, only outside production. Passwords are random and printed once. */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo accounts are only created in local/testing. Use: php artisan admission:make-staff');

            return;
        }

        $staffPassword = Str::password(16, symbols: false);
        $studentPassword = Str::password(16, symbols: false);

        User::factory()->staff()->create([
            'name' => 'Demo Staff',
            'username' => 'demo.staff',
            'email' => 'staff@example.test',
            'mobile' => '01700000001',
            'password' => $staffPassword,
        ]);

        User::factory()->create([
            'name' => 'Demo Student',
            'username' => 'demo.student',
            'email' => 'student@example.test',
            'mobile' => '01700000002',
            'password' => $studentPassword,
        ]);

        $this->command?->info("staff@example.test / {$staffPassword}");
        $this->command?->info("student@example.test / {$studentPassword}");
    }
}
