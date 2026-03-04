<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationExtra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationExtra>
 */
class ApplicationExtraFactory extends Factory
{
    protected $model = ApplicationExtra::class;

    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'study_from' => fake()->randomElement(['within_pakistan', 'overseas']),
            'prior_computer_knowledge' => fake()->boolean(),
            'has_computer' => fake()->boolean(),
            'internet_type' => fake()->randomElement(['dsl', 'cable', '3g4g', 'fiber', 'none']),
            'heard_about_us' => fake()->randomElement(['Facebook', 'WhatsApp', 'Friend', 'Newspaper', 'Website']),
            'scholarship_interest' => fake()->boolean(30),
        ];
    }
}
