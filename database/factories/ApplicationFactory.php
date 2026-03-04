<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Branch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'program_id' => Program::factory(),
            'branch_id' => Branch::factory(),
            'application_number' => 'BCI-2026-' . str_pad(fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'status' => 'draft',
            'study_mode' => fake()->randomElement(['at_home', 'virtual_campus']),
            'city' => fake()->randomElement(['Lahore', 'Islamabad', 'Faisalabad']),
            'submitted_at' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'admin_notes' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'submitted_at' => now()->subDays(5),
            'reviewed_at' => now(),
            'reviewed_by' => User::factory()->admin(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'submitted_at' => now()->subDays(5),
            'reviewed_at' => now(),
            'reviewed_by' => User::factory()->admin(),
            'admin_notes' => 'Incomplete documents.',
        ]);
    }
}
