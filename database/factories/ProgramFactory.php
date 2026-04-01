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
        ['name' => 'SSC-I General Science', 'short_name' => 'SSC-I', 'level' => 'ssc'],
        ['name' => 'SSC-I Arts', 'short_name' => 'SSC-I', 'level' => 'ssc'],
        ['name' => 'SSC-II General Science', 'short_name' => 'SSC-II', 'level' => 'ssc'],
        ['name' => 'SSC-II Arts', 'short_name' => 'SSC-II', 'level' => 'ssc'],
        ['name' => 'Pre-Intermediate', 'short_name' => 'Pre-Inter', 'level' => 'hssc'],
        ['name' => 'HSSC-I Pre-Medical', 'short_name' => 'HSSC-I', 'level' => 'hssc'],
        ['name' => 'HSSC-II Pre-Medical', 'short_name' => 'HSSC-II', 'level' => 'hssc'],
    ];

    private static int $index = 0;

    public function definition(): array
    {
        $program = self::$programs[self::$index % count(self::$programs)];
        self::$index++;

        return [
            'name' => $program['name'],
            'slug' => Str::slug($program['name']),
            'short_name' => $program['short_name'],
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
