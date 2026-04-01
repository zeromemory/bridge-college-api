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
            // SSC Programs
            ['name' => 'SSC-I General Science', 'short_name' => 'SSC-I', 'level' => 'ssc', 'sort_order' => 1],
            ['name' => 'SSC-I Arts', 'short_name' => 'SSC-I', 'level' => 'ssc', 'sort_order' => 2],
            ['name' => 'SSC-II General Science', 'short_name' => 'SSC-II', 'level' => 'ssc', 'sort_order' => 3],
            ['name' => 'SSC-II Arts', 'short_name' => 'SSC-II', 'level' => 'ssc', 'sort_order' => 4],

            // HSSC Programs
            ['name' => 'Pre-Intermediate', 'short_name' => 'Pre-Inter', 'level' => 'hssc', 'sort_order' => 5],
            ['name' => 'HSSC-I Pre-Medical', 'short_name' => 'HSSC-I', 'level' => 'hssc', 'sort_order' => 6],
            ['name' => 'HSSC-I Pre-Engineering', 'short_name' => 'HSSC-I', 'level' => 'hssc', 'sort_order' => 7],
            ['name' => 'HSSC-I ICS', 'short_name' => 'HSSC-I', 'level' => 'hssc', 'sort_order' => 8],
            ['name' => 'HSSC-I Commerce', 'short_name' => 'HSSC-I', 'level' => 'hssc', 'sort_order' => 9],
            ['name' => 'HSSC-I Humanities', 'short_name' => 'HSSC-I', 'level' => 'hssc', 'sort_order' => 10],
            ['name' => 'HSSC-II Pre-Medical', 'short_name' => 'HSSC-II', 'level' => 'hssc', 'sort_order' => 11],
            ['name' => 'HSSC-II Pre-Engineering', 'short_name' => 'HSSC-II', 'level' => 'hssc', 'sort_order' => 12],
            ['name' => 'HSSC-II ICS', 'short_name' => 'HSSC-II', 'level' => 'hssc', 'sort_order' => 13],
            ['name' => 'HSSC-II Commerce', 'short_name' => 'HSSC-II', 'level' => 'hssc', 'sort_order' => 14],
            ['name' => 'HSSC-II Humanities', 'short_name' => 'HSSC-II', 'level' => 'hssc', 'sort_order' => 15],

            // Short Course
            ['name' => 'Computer Short Course', 'short_name' => 'Short', 'level' => 'short_course', 'sort_order' => 16],
        ];

        foreach ($programs as $program) {
            Program::updateOrCreate(
                ['slug' => Str::slug($program['name'])],
                [
                    'name' => $program['name'],
                    'short_name' => $program['short_name'],
                    'level' => $program['level'],
                    'is_active' => true,
                    'sort_order' => $program['sort_order'],
                ],
            );
        }
    }
}
