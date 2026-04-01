<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Http\Requests\Admin\UpdateSubjectRequest;
use App\Models\Program;
use App\Models\Subject;
use App\Services\SubjectService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SubjectService $service,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(
            data: $this->service->list(),
            message: 'Subjects retrieved',
        );
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = $this->service->create($request->validated());

        return $this->success(
            data: $subject,
            message: 'Subject created',
            status: 201,
        );
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        $subject = $this->service->update($subject, $request->validated());

        return $this->success(
            data: $subject,
            message: 'Subject updated',
        );
    }

    public function destroy(Subject $subject): JsonResponse
    {
        $this->service->delete($subject);

        return $this->success(
            message: 'Subject deleted',
        );
    }

    public function programSubjects(Program $program): JsonResponse
    {
        $subjects = $program->subjects()->orderBy('sort_order')->get();

        return $this->success(
            data: $subjects,
            message: 'Program subjects retrieved',
        );
    }

    public function syncProgramSubjects(Request $request, Program $program): JsonResponse
    {
        $validated = $request->validate([
            'subjects' => ['required', 'array'],
            'subjects.*.subject_id' => ['required', 'exists:subjects,id'],
            'subjects.*.is_elective' => ['nullable', 'boolean'],
        ]);

        $this->service->syncProgramSubjects($program, $validated['subjects']);

        $subjects = $program->subjects()->orderBy('sort_order')->get();

        return $this->success(
            data: $subjects,
            message: 'Program subjects synced',
        );
    }
}
