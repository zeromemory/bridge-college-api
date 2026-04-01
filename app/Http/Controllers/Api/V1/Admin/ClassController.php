<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClassRequest;
use App\Http\Requests\Admin\UpdateClassRequest;
use App\Models\ClassRoom;
use App\Services\ClassService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ClassService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $classes = $this->service->list($request->only([
            'academic_session_id',
            'program_id',
            'branch_id',
        ]));

        return $this->success(
            data: $classes,
            message: 'Classes retrieved',
        );
    }

    public function store(StoreClassRequest $request): JsonResponse
    {
        $class = $this->service->create($request->validated());

        return $this->success(
            data: $class,
            message: 'Class created',
            status: 201,
        );
    }

    public function show(ClassRoom $class): JsonResponse
    {
        $class->load([
            'program', 'branch', 'academicSession', 'classTeacher',
            'subjectTeachers.subject', 'subjectTeachers.teacher',
        ]);

        return $this->success(
            data: $class,
            message: 'Class retrieved',
        );
    }

    public function update(UpdateClassRequest $request, ClassRoom $class): JsonResponse
    {
        $class = $this->service->update($class, $request->validated());

        return $this->success(
            data: $class,
            message: 'Class updated',
        );
    }

    public function destroy(ClassRoom $class): JsonResponse
    {
        $this->service->delete($class);

        return $this->success(
            message: 'Class deleted',
        );
    }

    public function assignTeacher(Request $request, ClassRoom $class): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:users,id'],
        ]);

        $assignment = $this->service->assignSubjectTeacher(
            $class,
            $validated['subject_id'],
            $validated['teacher_id'],
        );

        return $this->success(
            data: $assignment,
            message: 'Teacher assigned to subject',
        );
    }

    public function unassignTeacher(Request $request, ClassRoom $class): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $this->service->unassignSubjectTeacher($class, $validated['subject_id']);

        return $this->success(
            message: 'Teacher unassigned from subject',
        );
    }

    public function enrollStudent(Request $request, ClassRoom $class): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:users,id'],
        ]);

        $enrollment = $this->service->enrollStudent($class, $validated['student_id']);

        return $this->success(
            data: $enrollment,
            message: 'Student enrolled',
            status: 201,
        );
    }

    public function unenrollStudent(ClassRoom $class, int $studentId): JsonResponse
    {
        $this->service->unenrollStudent($class, $studentId);

        return $this->success(
            message: 'Student unenrolled',
        );
    }

    public function students(ClassRoom $class): JsonResponse
    {
        $students = $this->service->getStudents($class);

        return $this->success(
            data: $students,
            message: 'Class students retrieved',
        );
    }
}
