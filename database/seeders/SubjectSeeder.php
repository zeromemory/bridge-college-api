<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            // Common compulsory subjects
            ['name' => 'English', 'code' => 'ENG', 'sort_order' => 1],
            ['name' => 'Urdu', 'code' => 'URD', 'sort_order' => 2],
            ['name' => 'Islamiyat', 'code' => 'ISL', 'sort_order' => 3],
            ['name' => 'Pakistan Studies', 'code' => 'PKS', 'sort_order' => 4],

            // SSC Science subjects
            ['name' => 'Mathematics', 'code' => 'MATH', 'sort_order' => 5],
            ['name' => 'Physics', 'code' => 'PHY', 'sort_order' => 6],
            ['name' => 'Chemistry', 'code' => 'CHEM', 'sort_order' => 7],
            ['name' => 'Biology', 'code' => 'BIO', 'sort_order' => 8],
            ['name' => 'Computer Science', 'code' => 'CS', 'sort_order' => 9],

            // SSC Arts subjects
            ['name' => 'General Mathematics', 'code' => 'GMATH', 'sort_order' => 10],
            ['name' => 'General Science', 'code' => 'GSCI', 'sort_order' => 11],
            ['name' => 'Education', 'code' => 'EDU', 'sort_order' => 12],
            ['name' => 'Civics', 'code' => 'CIV', 'sort_order' => 13],

            // HSSC Commerce subjects
            ['name' => 'Accounting', 'code' => 'ACC', 'sort_order' => 14],
            ['name' => 'Economics', 'code' => 'ECO', 'sort_order' => 15],
            ['name' => 'Business Mathematics', 'code' => 'BMATH', 'sort_order' => 16],

            // HSSC Humanities subjects
            ['name' => 'History', 'code' => 'HIST', 'sort_order' => 17],

            // Short course subjects
            ['name' => 'Computer Skills', 'code' => 'CSKL', 'sort_order' => 18],
            ['name' => 'English Language', 'code' => 'ELNG', 'sort_order' => 19],
            ['name' => 'Graphic Design', 'code' => 'GDES', 'sort_order' => 20],
            ['name' => 'Web Development', 'code' => 'WDEV', 'sort_order' => 21],
        ];

        foreach ($subjects as $subject) {
            Subject::updateOrCreate(
                ['code' => $subject['code']],
                [
                    'name' => $subject['name'],
                    'is_active' => true,
                    'sort_order' => $subject['sort_order'],
                ],
            );
        }
    }
}
