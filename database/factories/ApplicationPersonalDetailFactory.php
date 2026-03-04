<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationPersonalDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationPersonalDetail>
 */
class ApplicationPersonalDetailFactory extends Factory
{
    protected $model = ApplicationPersonalDetail::class;

    public function definition(): array
    {
        $cnicFirst = fake()->numerify('#####');
        $cnicMiddle = fake()->numerify('#######');
        $cnicLast = fake()->randomDigit();

        $address = fake()->streetAddress() . ', ' . fake()->randomElement(['Lahore', 'Islamabad', 'Karachi']);

        return [
            'application_id' => Application::factory(),
            'father_name' => fake()->name('male'),
            'father_cnic' => "{$cnicFirst}-{$cnicMiddle}-{$cnicLast}",
            'father_phone' => '03' . fake()->numerify('#########'),
            'guardian_name' => null,
            'guardian_relationship' => null,
            'guardian_income' => null,
            'gender' => fake()->randomElement(['male', 'female']),
            'date_of_birth' => fake()->dateTimeBetween('-25 years', '-15 years')->format('Y-m-d'),
            'nationality' => 'Pakistani',
            'religion' => fake()->randomElement(['Islam', 'Christianity', 'Hinduism']),
            'mother_tongue' => fake()->randomElement(['Urdu', 'Punjabi', 'Sindhi', 'Pashto']),
            'postal_address' => $address,
            'permanent_address' => $address,
            'same_address' => true,
            'cnic_issuance_date' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
            'phone_landline' => '042-' . fake()->numerify('#######'),
        ];
    }
}
