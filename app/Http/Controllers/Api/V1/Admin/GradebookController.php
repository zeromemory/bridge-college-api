<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\Subject;
use App\Models\User;
use App\Services\GradebookService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradebookController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly GradebookService $service,
    ) {}

    /**
     * Class-wise results: per-student totals across published assessments.
     */
    public function classResults(Request $request, ClassRoom $class): JsonResponse
    {
        $subjectId = $request->query('subject_id') ? (int) $request->query('subject_id') : null;
        $type = $request->query('type');

        return $this->success(
            data: $this->service->getClassResults($class, $subjectId, $type),
            message: 'Class results retrieved',
        );
    }

    /**
     * Subject-wise results: per-student marks for one subject.
     */
    public function subjectResults(ClassRoom $class, Subject $subject): JsonResponse
    {
        return $this->success(
            data: $this->service->getSubjectResults($class, $subject->id),
            message: 'Subject results retrieved',
        );
    }

    /**
     * Student-wise results: all results across all classes for one student.
     */
    public function studentResults(User $student): JsonResponse
    {
        return $this->success(
            data: $this->service->getStudentResults($student->id),
            message: 'Student results retrieved',
        );
    }
}
