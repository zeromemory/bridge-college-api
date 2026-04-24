<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\ClassRoom;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return [
            'class_id' => ClassRoom::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory()->teacher(),
            'title' => fake()->randomElement([
                'Chapter ' . fake()->numberBetween(1, 15) . ' Test',
                'Monthly Assessment ' . fake()->randomElement(['Jan', 'Feb', 'Mar', 'Apr']),
                'Assignment ' . fake()->numberBetween(1, 10),
                'Quarterly Mock Exam',
                'Final Exam',
            ]),
            'type' => fake()->randomElement(['class_test', 'assignment', 'monthly_assessment', 'quarterly_mock_exam', 'final_exam']),
            'total_marks' => fake()->randomElement([10, 15, 20, 25, 50, 75, 100]),
            'date' => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'description' => fake()->optional(0.3)->sentence(),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }

    public function classTest(): static
    {
        return $this->state(fn () => ['type' => 'class_test', 'total_marks' => 25]);
    }

    public function assignment(): static
    {
        return $this->state(fn () => ['type' => 'assignment', 'total_marks' => 20]);
    }

    public function monthlyAssessment(): static
    {
        return $this->state(fn () => ['type' => 'monthly_assessment', 'total_marks' => 50]);
    }

    public function quarterlyMock(): static
    {
        return $this->state(fn () => ['type' => 'quarterly_mock_exam', 'total_marks' => 75]);
    }

    public function finalExam(): static
    {
        return $this->state(fn () => ['type' => 'final_exam', 'total_marks' => 100]);
    }
}
