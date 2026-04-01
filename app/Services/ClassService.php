<?php

namespace App\Services;

use App\Models\ClassRoom;
use App\Models\ClassSubjectTeacher;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Log;

class ClassService
{
    public function list(array $filters = [])
    {
        $query = ClassRoom::with(['program', 'branch', 'academicSession', 'classTeacher']);

        if (!empty($filters['academic_session_id'])) {
            $query->where('academic_session_id', $filters['academic_session_id']);
        }
        if (!empty($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }
        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        return $query->orderBy('name')->get();
    }

    public function create(array $data): ClassRoom
    {
        $class = ClassRoom::create($data);

        Log::info('Class created', ['class_id' => $class->id]);

        return $class->load(['program', 'branch', 'academicSession']);
    }

    public function update(ClassRoom $class, array $data): ClassRoom
    {
        $class->update($data);

        Log::info('Class updated', ['class_id' => $class->id]);

        return $class->fresh(['program', 'branch', 'academicSession', 'classTeacher']);
    }

    public function delete(ClassRoom $class): void
    {
        $class->delete();

        Log::info('Class deleted', ['class_id' => $class->id]);
    }

    public function assignSubjectTeacher(ClassRoom $class, int $subjectId, int $teacherId): ClassSubjectTeacher
    {
        $assignment = ClassSubjectTeacher::updateOrCreate(
            ['class_id' => $class->id, 'subject_id' => $subjectId],
            ['teacher_id' => $teacherId],
        );

        Log::info('Teacher assigned to subject in class', [
            'class_id' => $class->id,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
        ]);

        return $assignment->load(['subject', 'teacher']);
    }

    public function unassignSubjectTeacher(ClassRoom $class, int $subjectId): void
    {
        ClassSubjectTeacher::where('class_id', $class->id)
            ->where('subject_id', $subjectId)
            ->delete();

        Log::info('Teacher unassigned from subject in class', [
            'class_id' => $class->id,
            'subject_id' => $subjectId,
        ]);
    }

    public function enrollStudent(ClassRoom $class, int $studentId): Enrollment
    {
        $existing = Enrollment::where('class_id', $class->id)
            ->where('student_id', $studentId)
            ->first();

        if ($existing) {
            if ($existing->status === 'active') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'student_id' => ['This student is already enrolled in this class.'],
                ]);
            }
            $existing->update(['status' => 'active', 'enrolled_at' => now()]);
            return $existing->load('student');
        }

        $enrollment = Enrollment::create([
            'student_id' => $studentId,
            'class_id' => $class->id,
            'enrolled_at' => now(),
            'status' => 'active',
        ]);

        Log::info('Student enrolled in class', [
            'class_id' => $class->id,
            'student_id' => $studentId,
        ]);

        return $enrollment->load('student');
    }

    public function unenrollStudent(ClassRoom $class, int $studentId): void
    {
        Enrollment::where('class_id', $class->id)
            ->where('student_id', $studentId)
            ->update(['status' => 'withdrawn']);

        Log::info('Student unenrolled from class', [
            'class_id' => $class->id,
            'student_id' => $studentId,
        ]);
    }

    public function getStudents(ClassRoom $class)
    {
        return $class->enrollments()
            ->with('student')
            ->get();
    }
}
