<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Models\User;
use App\Services\TeacherService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TeacherService $service,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(
            data: $this->service->list(),
            message: 'Teachers retrieved',
        );
    }

    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $teacher = $this->service->create($request->validated());

        return $this->success(
            data: $teacher,
            message: 'Teacher created',
            status: 201,
        );
    }

    public function show(User $teacher): JsonResponse
    {
        if (!$teacher->isTeacher()) {
            return $this->error(
                message: 'User is not a teacher',
                errorCode: 'NOT_FOUND',
                status: 404,
            );
        }

        return $this->success(
            data: $this->service->show($teacher),
            message: 'Teacher retrieved',
        );
    }

    public function update(Request $request, User $teacher): JsonResponse
    {
        if (!$teacher->isTeacher()) {
            return $this->error(
                message: 'User is not a teacher',
                errorCode: 'NOT_FOUND',
                status: 404,
            );
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', "unique:users,email,{$teacher->id}"],
            'mobile' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $teacher = $this->service->update($teacher, $validated);

        return $this->success(
            data: $teacher,
            message: 'Teacher updated',
        );
    }

    public function toggleStatus(User $teacher): JsonResponse
    {
        if (!$teacher->isTeacher()) {
            return $this->error(
                message: 'User is not a teacher',
                errorCode: 'NOT_FOUND',
                status: 404,
            );
        }

        $teacher = $this->service->toggleStatus($teacher);

        return $this->success(
            data: $teacher,
            message: $teacher->is_active ? 'Teacher activated' : 'Teacher deactivated',
        );
    }

    public function resendSetup(User $teacher): JsonResponse
    {
        if (!$teacher->isTeacher()) {
            return $this->error(
                message: 'User is not a teacher',
                errorCode: 'NOT_FOUND',
                status: 404,
            );
        }

        try {
            $this->service->resendSetupLink($teacher);
        } catch (\DomainException $e) {
            return $this->error(
                message: $e->getMessage(),
                errorCode: 'DUPLICATE_ENTRY',
                status: 409,
            );
        }

        return $this->success(
            message: 'Setup link resent to teacher email.',
        );
    }
}
