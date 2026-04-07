<?php

namespace App\Policies;

use App\Models\ClassRoom;
use App\Models\ClassSubjectTeacher;
use App\Models\User;

class ClassRoomPolicy
{
    /**
     * A teacher may access a class if they are either:
     *   - the homeroom (class_teacher_id), or
     *   - assigned as a subject teacher in that class.
     *
     * Used by teacher-side LMS endpoints (class detail, students,
     * materials list).
     */
    public function accessAsTeacher(User $user, ClassRoom $class): bool
    {
        if (! $user->isTeacher()) {
            return false;
        }

        if ($class->class_teacher_id === $user->id) {
            return true;
        }

        return ClassSubjectTeacher::where('class_id', $class->id)
            ->where('teacher_id', $user->id)
            ->exists();
    }
}
