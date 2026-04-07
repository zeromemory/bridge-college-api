<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreClassMaterialRequest;
use App\Models\ClassMaterial;
use App\Models\ClassRoom;
use App\Services\ClassMaterialService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassMaterialController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ClassMaterialService $service,
    ) {}

    public function index(Request $request, ClassRoom $class): JsonResponse
    {
        return $this->success(
            data: $this->service->listForTeacherClass($request->user(), $class),
            message: 'Materials retrieved',
        );
    }

    public function store(StoreClassMaterialRequest $request, ClassRoom $class): JsonResponse
    {
        try {
            $material = $this->service->create(
                $request->user(),
                $class,
                $request->validated(),
                $request->file('file'),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error(
                message: $e->getMessage(),
                errorCode: 'UPLOAD_ERROR',
                status: 422,
            );
        }

        return $this->success(
            data: $material,
            message: 'Material uploaded',
            status: 201,
        );
    }

    public function destroy(Request $request, ClassRoom $class, ClassMaterial $material): JsonResponse
    {
        // URL safety: ensure the material actually belongs to this class.
        if ($material->class_id !== $class->id) {
            return $this->error(
                message: 'Material does not belong to this class.',
                errorCode: 'NOT_FOUND',
                status: 404,
            );
        }

        $this->service->delete($request->user(), $material);

        return $this->success(message: 'Material deleted');
    }
}
