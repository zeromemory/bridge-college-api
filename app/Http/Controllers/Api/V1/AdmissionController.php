<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\CreateApplicationRequest;
use App\Http\Requests\Admission\UpdateExtrasRequest;
use App\Http\Requests\Admission\UpdatePersonalDetailsRequest;
use App\Http\Requests\Admission\UploadDocumentRequest;
use App\Models\Application;
use App\Services\ApplicationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {}

    public function store(CreateApplicationRequest $request): JsonResponse
    {
        $application = $this->applicationService->create(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            data: ['application' => $application->load(['program', 'branch'])],
            message: 'Application created successfully.',
            status: 201,
        );
    }

    public function update(CreateApplicationRequest $request, int $id): JsonResponse
    {
        $application = $this->findApplication($request, $id);

        $request->user()->can('update', $application)
            ?: abort(403, 'Unauthorized.');

        $application->update($request->validated());

        return $this->success(
            data: ['application' => $application->load(['program', 'branch'])],
            message: 'Application updated successfully.',
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $application = $this->findApplication($request, $id);

        $request->user()->can('view', $application)
            ?: abort(403, 'Unauthorized.');

        return $this->success(
            data: ['application' => $this->applicationService->getReview($application)],
            message: 'Application retrieved successfully.',
        );
    }

    public function updatePersonalDetails(UpdatePersonalDetailsRequest $request, int $id): JsonResponse
    {
        $application = $this->findApplication($request, $id);

        $request->user()->can('update', $application)
            ?: abort(403, 'Unauthorized.');

        $application = $this->applicationService->updatePersonalDetails(
            $application,
            $request->validated(),
        );

        return $this->success(
            data: ['application' => $application],
            message: 'Personal details saved successfully.',
        );
    }

    public function updateExtras(UpdateExtrasRequest $request, int $id): JsonResponse
    {
        $application = $this->findApplication($request, $id);

        $request->user()->can('update', $application)
            ?: abort(403, 'Unauthorized.');

        $application = $this->applicationService->updateExtras(
            $application,
            $request->validated(),
        );

        return $this->success(
            data: ['application' => $application],
            message: 'Additional information saved successfully.',
        );
    }

    public function uploadDocument(UploadDocumentRequest $request, int $id): JsonResponse
    {
        $application = $this->findApplication($request, $id);

        $request->user()->can('uploadDocument', $application)
            ?: abort(403, 'Unauthorized.');

        try {
            $document = $this->applicationService->uploadDocument(
                $application,
                $request->file('file'),
                $request->input('document_type'),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error(
                message: $e->getMessage(),
                errorCode: 'UPLOAD_ERROR',
                status: 422,
            );
        }

        return $this->success(
            data: ['document' => $document],
            message: 'Document uploaded successfully.',
            status: 201,
        );
    }

    public function deleteDocument(Request $request, int $id, int $documentId): JsonResponse
    {
        $application = $this->findApplication($request, $id);

        $request->user()->can('deleteDocument', $application)
            ?: abort(403, 'Unauthorized.');

        $this->applicationService->deleteDocument($application, $documentId);

        return $this->success(
            message: 'Document deleted successfully.',
        );
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $application = $this->findApplication($request, $id);

        $request->user()->can('view', $application)
            ?: abort(403, 'Unauthorized.');

        return $this->success(
            data: ['application' => $this->applicationService->getReview($application)],
            message: 'Application review data retrieved successfully.',
        );
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $application = $this->findApplication($request, $id);

        $request->user()->can('submit', $application)
            ?: abort(403, 'Unauthorized.');

        $application = $this->applicationService->submit($application);

        return $this->success(
            data: ['application' => $application],
            message: 'Application submitted successfully.',
        );
    }

    private function findApplication(Request $request, int $id): Application
    {
        return $request->user()->applications()->findOrFail($id);
    }
}
