<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationEducation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationEducation>
 */
class ApplicationEducationFactory extends Factory
{
    protected $model = ApplicationEducation::class;

    public function definition(): array
    {
        $totalMarks = fake()->randomElement([550, 850, 1050, 1100]);
        $percentage = fake()->numberBetween(40, 95);
        $obtainedMarks = (int) round($totalMarks * $percentage / 100);

        return [
            'application_id' => Application::factory(),
            'qualification' => fake()->randomElement(['Matric', 'Inter', 'Middle', 'Primary']),
            'board_university' => fake()->randomElement([
                'BISE Lahore',
                'BISE Rawalpindi',
                'BISE Faisalabad',
                'Federal Board',
                'Aga Khan Board',
            ]),
            'roll_no' => (string) fake()->numberBetween(100000, 999999),
            'registration_no' => (string) fake()->numberBetween(10000, 99999),
            'exam_type' => fake()->randomElement(['Annual', 'Supplementary']),
            'exam_year' => fake()->numberBetween(2018, 2025),
            'total_marks' => $totalMarks,
            'obtained_marks' => $obtainedMarks,
            'sort_order' => 0,
        ];
    }
}
