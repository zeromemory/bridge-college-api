<?php

namespace App\Services;

use App\Models\ClassMaterial;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class StudentLmsService
{
    /**
     * Atomic snapshot for the student LMS view: enrolled class + subjects
     * with assigned teachers + materials grouped by subject. Returned as
     * one payload (one round trip, one loading state on the frontend).
     *
     * Returns ['class' => null, 'subjects' => []] if the student has no
     * active enrollment.
     */
    public function myClass(User $student): array
    {
        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->with([
                'classRoom.program:id,name,short_name',
                'classRoom.branch:id,name',
                'classRoom.academicSession:id,name',
                'classRoom.subjectTeachers.subject:id,name,code',
                'classRoom.subjectTeachers.teacher:id,name',
            ])
            ->first();

        if (! $enrollment) {
            return [
                'class' => null,
                'subjects' => [],
            ];
        }

        $class = $enrollment->classRoom;

        $materialsBySubject = ClassMaterial::where('class_id', $class->id)
            ->with('uploader:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('subject_id');

        $subjects = $class->subjectTeachers->map(function ($assignment) use ($materialsBySubject) {
            return [
                'subject' => $assignment->subject,
                'teacher' => $assignment->teacher,
                'materials' => $materialsBySubject->get($assignment->subject_id, collect())->values(),
            ];
        })->values();

        return [
            'class' => [
                'id' => $class->id,
                'name' => $class->name,
                'program' => $class->program,
                'branch' => $class->branch,
                'academic_session' => $class->academicSession,
            ],
            'subjects' => $subjects,
        ];
    }

    /**
     * Re-queries enrollment at request time (no caching). Throws 403 if
     * the student is not actively enrolled in the class that owns the
     * requested material.
     */
    public function ensureStudentCanAccessMaterial(User $student, ClassMaterial $material): void
    {
        $hasActive = Enrollment::where('student_id', $student->id)
            ->where('class_id', $material->class_id)
            ->where('status', 'active')
            ->exists();

        if (! $hasActive) {
            throw new AuthorizationException('You do not have access to this material.');
        }
    }
}
