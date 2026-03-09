<?php

namespace Database\Seeders;

use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        $programs = [
            ['name' => 'SSC-I (9th Class)', 'level' => 'ssc', 'sort_order' => 1],
            ['name' => 'SSC-II (Matric)', 'level' => 'ssc', 'sort_order' => 2],
            ['name' => 'Pre-Intermediate', 'level' => 'hssc', 'sort_order' => 3],
            ['name' => 'HSSC-I (1st Year)', 'level' => 'hssc', 'sort_order' => 4],
            ['name' => 'HSSC-II (2nd Year)', 'level' => 'hssc', 'sort_order' => 5],
            ['name' => 'Computer Short Course', 'level' => 'short_course', 'sort_order' => 6],
        ];

        foreach ($programs as $program) {
            Program::firstOrCreate(
                ['slug' => Str::slug($program['name'])],
                [
                    'name' => $program['name'],
                    'level' => $program['level'],
                    'is_active' => true,
                    'sort_order' => $program['sort_order'],
                ],
            );
        }
    }
}
