<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'class_id' => ClassRoom::factory(),
            'student_id' => User::factory(),
            'marked_by' => User::factory()->teacher(),
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'status' => fake()->randomElement(['present', 'absent', 'late', 'leave']),
            'remarks' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'present',
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'absent',
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'late',
        ]);
    }

    public function leave(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'leave',
        ]);
    }
}
