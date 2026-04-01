<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class ProgramSubjectSeeder extends Seeder
{
    public function run(): void
    {
        // Build subject code => id map
        $subjects = Subject::pluck('id', 'code')->toArray();

        // Define subject assignments per program (by slug => [subject_codes])
        $assignments = [
            // SSC-I General Science
            'ssc-i-general-science' => [
                'ENG', 'URD', 'ISL', 'PKS', 'MATH', 'PHY', 'CHEM',
                ['code' => 'BIO', 'is_elective' => true],
                ['code' => 'CS', 'is_elective' => true],
            ],
            // SSC-I Arts
            'ssc-i-arts' => [
                'ENG', 'URD', 'ISL', 'PKS', 'GMATH', 'GSCI',
                ['code' => 'EDU', 'is_elective' => true],
                ['code' => 'CIV', 'is_elective' => true],
            ],
            // SSC-II General Science
            'ssc-ii-general-science' => [
                'ENG', 'URD', 'ISL', 'PKS', 'MATH', 'PHY', 'CHEM',
                ['code' => 'BIO', 'is_elective' => true],
                ['code' => 'CS', 'is_elective' => true],
            ],
            // SSC-II Arts
            'ssc-ii-arts' => [
                'ENG', 'URD', 'ISL', 'PKS', 'GMATH', 'GSCI',
                ['code' => 'EDU', 'is_elective' => true],
                ['code' => 'CIV', 'is_elective' => true],
            ],
            // Pre-Intermediate (foundation — all core subjects)
            'pre-intermediate' => [
                'ENG', 'URD', 'ISL', 'PKS', 'MATH', 'PHY', 'CHEM', 'BIO', 'CS',
            ],
            // HSSC-I Pre-Medical
            'hssc-i-pre-medical' => ['ENG', 'URD', 'ISL', 'PKS', 'PHY', 'CHEM', 'BIO'],
            // HSSC-I Pre-Engineering
            'hssc-i-pre-engineering' => ['ENG', 'URD', 'ISL', 'PKS', 'PHY', 'CHEM', 'MATH'],
            // HSSC-I ICS
            'hssc-i-ics' => ['ENG', 'URD', 'ISL', 'PKS', 'PHY', 'CS', 'MATH'],
            // HSSC-I Commerce
            'hssc-i-commerce' => ['ENG', 'URD', 'ISL', 'PKS', 'ACC', 'ECO', 'BMATH'],
            // HSSC-I Humanities
            'hssc-i-humanities' => ['ENG', 'URD', 'ISL', 'PKS', 'CIV', 'HIST', 'EDU'],
            // HSSC-II Pre-Medical
            'hssc-ii-pre-medical' => ['ENG', 'URD', 'ISL', 'PKS', 'PHY', 'CHEM', 'BIO'],
            // HSSC-II Pre-Engineering
            'hssc-ii-pre-engineering' => ['ENG', 'URD', 'ISL', 'PKS', 'PHY', 'CHEM', 'MATH'],
            // HSSC-II ICS
            'hssc-ii-ics' => ['ENG', 'URD', 'ISL', 'PKS', 'PHY', 'CS', 'MATH'],
            // HSSC-II Commerce
            'hssc-ii-commerce' => ['ENG', 'URD', 'ISL', 'PKS', 'ACC', 'ECO', 'BMATH'],
            // HSSC-II Humanities
            'hssc-ii-humanities' => ['ENG', 'URD', 'ISL', 'PKS', 'CIV', 'HIST', 'EDU'],
            // Computer Short Course
            'computer-short-course' => ['CSKL', 'ELNG', 'GDES', 'WDEV'],
        ];

        foreach ($assignments as $slug => $subjectCodes) {
            $program = Program::where('slug', $slug)->first();
            if (!$program) {
                continue;
            }

            $syncData = [];
            foreach ($subjectCodes as $entry) {
                if (is_array($entry)) {
                    $subjectId = $subjects[$entry['code']] ?? null;
                    if ($subjectId) {
                        $syncData[$subjectId] = ['is_elective' => $entry['is_elective'] ?? false];
                    }
                } else {
                    $subjectId = $subjects[$entry] ?? null;
                    if ($subjectId) {
                        $syncData[$subjectId] = ['is_elective' => false];
                    }
                }
            }

            $program->subjects()->sync($syncData);
        }
    }
}
