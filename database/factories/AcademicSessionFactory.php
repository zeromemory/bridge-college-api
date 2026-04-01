<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    protected $model = AcademicSession::class;

    private static int $yearCounter = 2024;

    public function definition(): array
    {
        $year = self::$yearCounter++;

        return [
            'name' => "{$year}-" . ($year + 1),
            'start_date' => "{$year}-04-01",
            'end_date' => ($year + 1) . '-03-31',
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
