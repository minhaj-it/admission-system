<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(10000000, 99999999);

        return [
            'name' => fake()->name(),
            'username' => 'user' . $n,
            'email' => 'user' . $n . '@example.test',
            'mobile' => '017' . $n,
            'account_type' => User::TYPE_STUDENT,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password123'),
            'remember_token' => Str::random(10),
        ];
    }

    /** Staff account; the built-in role is looked up by slug (super-admin by default). */
    public function staff(string $role = Role::SUPER_ADMIN): static
    {
        return $this->state(fn () => [
            'account_type' => User::TYPE_STAFF,
            'role_id' => Role::where('slug', $role)->value('id'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => User::STATUS_INACTIVE]);
    }
}
