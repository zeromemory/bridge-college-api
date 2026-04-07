<?php

namespace App\Services;

use App\Models\ClassRoom;
use App\Models\ClassSubjectTeacher;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

class TeacherDashboardService
{
    /**
     * Return all classes the given teacher is involved in:
     *   - homeroom (class_teacher_id), OR
     *   - assigned as a subject teacher (class_subject_teacher row).
     *
     * Each class is loaded with the subjects this specific teacher teaches
     * in it (filtered) plus the active enrollment count.
     */
    public function myClasses(User $teacher): Collection
    {
        $classIds = ClassRoom::where('class_teacher_id', $teacher->id)
            ->pluck('id')
            ->merge(
                ClassSubjectTeacher::where('teacher_id', $teacher->id)->pluck('class_id')
            )
            ->unique()
            ->values();

        return ClassRoom::whereIn('id', $classIds)
            ->with([
                'program:id,name,short_name',
                'branch:id,name',
                'academicSession:id,name',
                'subjectTeachers' => fn ($q) => $q
                    ->where('teacher_id', $teacher->id)
                    ->with('subject:id,name,code'),
            ])
            ->withCount(['enrollments' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get();
    }

    /**
     * Shared guard. Throws AuthorizationException (→ 403) if the teacher
     * has no relationship to the class. Used by service methods that
     * accept a ClassRoom parameter.
     */
    public function ensureTeacherCanAccessClass(User $teacher, ClassRoom $class): void
    {
        if ($class->class_teacher_id === $teacher->id) {
            return;
        }

        $hasSubject = ClassSubjectTeacher::where('class_id', $class->id)
            ->where('teacher_id', $teacher->id)
            ->exists();

        if (! $hasSubject) {
            throw new AuthorizationException('You do not have access to this class.');
        }
    }

    public function classDetail(User $teacher, ClassRoom $class): ClassRoom
    {
        $this->ensureTeacherCanAccessClass($teacher, $class);

        return $class->load([
            'program:id,name,short_name',
            'branch:id,name',
            'academicSession:id,name',
            'classTeacher:id,name',
            'subjectTeachers' => fn ($q) => $q
                ->where('teacher_id', $teacher->id)
                ->with('subject:id,name,code'),
        ])->loadCount(['enrollments' => fn ($q) => $q->where('status', 'active')]);
    }

    public function classStudents(User $teacher, ClassRoom $class): Collection
    {
        $this->ensureTeacherCanAccessClass($teacher, $class);

        return $class->enrollments()
            ->where('status', 'active')
            ->with('student:id,name,cnic,email,mobile')
            ->orderBy('enrolled_at')
            ->get();
    }
}
