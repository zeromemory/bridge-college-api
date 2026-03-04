<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    protected $model = ApplicationDocument::class;

    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'document_type' => fake()->randomElement([
                'photo', 'cnic_front', 'cnic_back', 'father_cnic', 'marks_sheet',
            ]),
            'file_path' => 'documents/' . fake()->uuid() . '.jpg',
            'original_name' => fake()->word() . '.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(50000, 500000),
            'uploaded_at' => now(),
        ];
    }
}
