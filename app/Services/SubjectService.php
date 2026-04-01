<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Subject;
use Illuminate\Support\Facades\Log;

class SubjectService
{
    public function list()
    {
        return Subject::orderBy('sort_order')->get();
    }

    public function create(array $data): Subject
    {
        $subject = Subject::create($data);

        Log::info('Subject created', ['subject_id' => $subject->id]);

        return $subject;
    }

    public function update(Subject $subject, array $data): Subject
    {
        $subject->update($data);

        Log::info('Subject updated', ['subject_id' => $subject->id]);

        return $subject->fresh();
    }

    public function delete(Subject $subject): void
    {
        $subject->delete();

        Log::info('Subject deleted', ['subject_id' => $subject->id]);
    }

    public function syncProgramSubjects(Program $program, array $subjects): void
    {
        // $subjects is an array of ['subject_id' => id, 'is_elective' => bool]
        $syncData = [];
        foreach ($subjects as $entry) {
            $syncData[$entry['subject_id']] = [
                'is_elective' => $entry['is_elective'] ?? false,
            ];
        }

        $program->subjects()->sync($syncData);

        Log::info('Program subjects synced', [
            'program_id' => $program->id,
            'subject_count' => count($syncData),
        ]);
    }
}
