<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Services\TeacherDashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TeacherDashboardService $service,
    ) {}

    public function myClasses(Request $request): JsonResponse
    {
        return $this->success(
            data: $this->service->myClasses($request->user()),
            message: 'Classes retrieved',
        );
    }

    public function classDetail(Request $request, ClassRoom $class): JsonResponse
    {
        return $this->success(
            data: $this->service->classDetail($request->user(), $class),
            message: 'Class detail retrieved',
        );
    }

    public function classStudents(Request $request, ClassRoom $class): JsonResponse
    {
        return $this->success(
            data: $this->service->classStudents($request->user(), $class),
            message: 'Class students retrieved',
        );
    }
}
