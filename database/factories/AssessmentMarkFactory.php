<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentMark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentMark>
 */
class AssessmentMarkFactory extends Factory
{
    protected $model = AssessmentMark::class;

    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'student_id' => User::factory(),
            'marks_obtained' => fake()->randomFloat(1, 5, 25),
            'is_absent' => false,
            'remarks' => fake()->optional(0.2)->sentence(),
        ];
    }

    public function absent(): static
    {
        return $this->state(fn () => [
            'marks_obtained' => null,
            'is_absent' => true,
        ]);
    }

    public function notGraded(): static
    {
        return $this->state(fn () => [
            'marks_obtained' => null,
            'is_absent' => false,
        ]);
    }
}
