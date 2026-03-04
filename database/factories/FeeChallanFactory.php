<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\FeeChallan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeChallan>
 */
class FeeChallanFactory extends Factory
{
    protected $model = FeeChallan::class;

    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'challan_number' => 'BCI-FEE-2026-' . str_pad(fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'amount' => fake()->randomElement([5000.00, 10000.00, 15000.00, 20000.00]),
            'due_date' => fake()->dateTimeBetween('+7 days', '+30 days')->format('Y-m-d'),
            'status' => 'pending',
            'paid_at' => null,
            'payment_reference' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => 'TXN-' . fake()->numerify('########'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'due_date' => now()->subDays(7)->format('Y-m-d'),
        ]);
    }
}
