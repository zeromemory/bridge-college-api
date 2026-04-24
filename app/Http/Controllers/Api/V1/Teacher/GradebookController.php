<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\SaveMarksRequest;
use App\Http\Requests\Teacher\StoreAssessmentRequest;
use App\Http\Requests\Teacher\UpdateAssessmentRequest;
use App\Models\Assessment;
use App\Models\ClassRoom;
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
     * List assessments for a class (filterable by subject_id, type).
     */
    public function index(Request $request, ClassRoom $class): JsonResponse
    {
        $subjectId = $request->query('subject_id') ? (int) $request->query('subject_id') : null;
        $type = $request->query('type');

        return $this->success(
            data: $this->service->getClassAssessments(
                $request->user(),
                $class,
                $subjectId,
                $type,
            ),
            message: 'Assessments retrieved',
        );
    }

    /**
     * Create a new assessment.
     */
    public function store(StoreAssessmentRequest $request, ClassRoom $class): JsonResponse
    {
        $assessment = $this->service->createAssessment(
            $request->user(),
            $class,
            $request->validated(),
        );

        return $this->success(
            data: $assessment,
            message: 'Assessment created',
            status: 201,
        );
    }

    /**
     * Show assessment with all enrolled students and their marks.
     */
    public function show(Request $request, Assessment $assessment): JsonResponse
    {
        return $this->success(
            data: $this->service->getAssessmentWithMarks(
                $request->user(),
                $assessment,
            ),
            message: 'Assessment retrieved',
        );
    }

    /**
     * Update assessment metadata.
     */
    public function update(UpdateAssessmentRequest $request, Assessment $assessment): JsonResponse
    {
        try {
            $updated = $this->service->updateAssessment(
                $request->user(),
                $assessment,
                $request->validated(),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error(
                message: $e->getMessage(),
                errorCode: 'VALIDATION_ERROR',
                status: 422,
            );
        }

        return $this->success(
            data: $updated,
            message: 'Assessment updated',
        );
    }

    /**
     * Soft-delete an unpublished assessment.
     */
    public function destroy(Request $request, Assessment $assessment): JsonResponse
    {
        try {
            $this->service->deleteAssessment($request->user(), $assessment);
        } catch (\InvalidArgumentException $e) {
            return $this->error(
                message: $e->getMessage(),
                errorCode: 'VALIDATION_ERROR',
                status: 422,
            );
        }

        return $this->success(
            message: 'Assessment deleted',
        );
    }

    /**
     * Publish an assessment (visible to students).
     */
    public function publish(Request $request, Assessment $assessment): JsonResponse
    {
        $updated = $this->service->publishAssessment($request->user(), $assessment);

        return $this->success(
            data: $updated,
            message: 'Assessment published',
        );
    }

    /**
     * Unpublish an assessment (hidden from students).
     */
    public function unpublish(Request $request, Assessment $assessment): JsonResponse
    {
        $updated = $this->service->unpublishAssessment($request->user(), $assessment);

        return $this->success(
            data: $updated,
            message: 'Assessment unpublished',
        );
    }

    /**
     * Bulk save/update marks for an assessment.
     */
    public function saveMarks(SaveMarksRequest $request, Assessment $assessment): JsonResponse
    {
        try {
            $results = $this->service->saveMarks(
                $request->user(),
                $assessment,
                $request->validated()['marks'],
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error(
                message: $e->getMessage(),
                errorCode: 'VALIDATION_ERROR',
                status: 422,
            );
        }

        return $this->success(
            data: $results,
            message: 'Marks saved',
            status: 201,
        );
    }

    /**
     * Grade summary for a specific student in a class.
     */
    public function studentSummary(Request $request, ClassRoom $class, User $student): JsonResponse
    {
        try {
            $summary = $this->service->getStudentGradeSummary(
                $request->user(),
                $class,
                $student->id,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error(
                message: $e->getMessage(),
                errorCode: 'VALIDATION_ERROR',
                status: 422,
            );
        }

        return $this->success(
            data: $summary,
            message: 'Student grade summary retrieved',
        );
    }
}
