<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'telegram_id' => fake()->unique()->numberBetween(100_000_000, 9_999_999_999),
            'username' => fake()->unique()->userName(),
            'full_name' => fake()->name(),
            'phone' => null,
            'role' => Role::Student,
            'status' => UserStatus::Active,
            'registered_at' => now(),
        ];
    }

    public function teacher(): static
    {
        return $this->state(fn () => [
            'role' => Role::Teacher,
            'subject' => fake()->randomElement(['Matematika', 'Fizika', 'Ingliz tili', 'Informatika']),
            'bio' => fake()->sentence(6),
            'phone' => '+998' . fake()->numerify('#########'),
        ]);
    }

    public function moderator(): static
    {
        return $this->state(fn () => ['role' => Role::Moderator]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Blocked, 'blocked_reason' => 'Test']);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Pending]);
    }
}
