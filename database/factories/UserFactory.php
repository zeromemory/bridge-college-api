<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $cnicFirst = fake()->numerify('#####');
        $cnicMiddle = fake()->numerify('#######');
        $cnicLast = fake()->randomDigit();

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'cnic' => "{$cnicFirst}-{$cnicMiddle}-{$cnicLast}",
            'mobile' => '03' . fake()->numerify('#########'),
            'nationality' => fake()->randomElement(['pakistani', 'foreign_national']),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    public function teacher(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'teacher',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
