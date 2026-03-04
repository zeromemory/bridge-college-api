<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    protected $model = Program::class;

    private static array $programs = [
        ['name' => 'SSC-I (9th Class)', 'level' => 'ssc'],
        ['name' => 'SSC-II (Matric)', 'level' => 'ssc'],
        ['name' => 'Pre-Intermediate', 'level' => 'hssc'],
        ['name' => 'HSSC-I (1st Year)', 'level' => 'hssc'],
        ['name' => 'HSSC-II (2nd Year)', 'level' => 'hssc'],
        ['name' => 'Computer Short Course', 'level' => 'short_course'],
        ['name' => 'English Language Course', 'level' => 'short_course'],
    ];

    private static int $index = 0;

    public function definition(): array
    {
        $program = self::$programs[self::$index % count(self::$programs)];
        self::$index++;

        return [
            'name' => $program['name'],
            'slug' => Str::slug($program['name']),
            'level' => $program['level'],
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => self::$index,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
