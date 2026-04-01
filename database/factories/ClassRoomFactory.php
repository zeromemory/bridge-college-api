<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassRoom;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassRoom>
 */
class ClassRoomFactory extends Factory
{
    protected $model = ClassRoom::class;

    public function definition(): array
    {
        return [
            'name' => 'Section ' . fake()->randomLetter(),
            'program_id' => Program::factory(),
            'branch_id' => Branch::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'class_teacher_id' => null,
            'capacity' => fake()->numberBetween(20, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
