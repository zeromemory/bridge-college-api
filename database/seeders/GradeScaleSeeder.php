<?php

namespace Database\Seeders;

use App\Models\GradeScale;
use Illuminate\Database\Seeder;

class GradeScaleSeeder extends Seeder
{
    public function run(): void
    {
        $scales = [
            ['grade' => 'A+', 'min_percentage' => 90.00, 'max_percentage' => 100.00, 'remarks' => 'Exceptional', 'sort_order' => 1],
            ['grade' => 'A',  'min_percentage' => 80.00, 'max_percentage' => 89.99,  'remarks' => 'Excellent',   'sort_order' => 2],
            ['grade' => 'B',  'min_percentage' => 70.00, 'max_percentage' => 79.99,  'remarks' => 'Very Good',   'sort_order' => 3],
            ['grade' => 'C',  'min_percentage' => 60.00, 'max_percentage' => 69.99,  'remarks' => 'Good',        'sort_order' => 4],
            ['grade' => 'D',  'min_percentage' => 50.00, 'max_percentage' => 59.99,  'remarks' => 'Satisfactory','sort_order' => 5],
            ['grade' => 'F',  'min_percentage' => 0.00,  'max_percentage' => 49.99,  'remarks' => 'Fail',        'sort_order' => 6],
        ];

        foreach ($scales as $scale) {
            GradeScale::updateOrCreate(
                ['grade' => $scale['grade']],
                $scale,
            );
        }
    }
}
