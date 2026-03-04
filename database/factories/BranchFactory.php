<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    private static array $cities = ['Lahore', 'Islamabad', 'Faisalabad', 'Karachi', 'Rawalpindi'];

    public function definition(): array
    {
        $city = fake()->randomElement(self::$cities);

        return [
            'name' => $city . ' — ' . fake()->streetName(),
            'address' => fake()->streetAddress() . ', ' . $city,
            'city' => $city,
            'phones' => ['042-' . fake()->numerify('#######')],
            'whatsapp' => '+9230' . fake()->numerify('########'),
            'whatsapp_link' => 'https://wa.me/9230' . fake()->numerify('########'),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
