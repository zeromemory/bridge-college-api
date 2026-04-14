<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassMaterial;
use App\Services\AttendanceService;
use App\Services\StudentLmsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentLmsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly StudentLmsService $service,
        private readonly AttendanceService $attendanceService,
    ) {}

    public function myClass(Request $request): JsonResponse
    {
        return $this->success(
            data: $this->service->myClass($request->user()),
            message: 'Class retrieved',
        );
    }

    /**
     * Streams a file material to the student. Re-checks active enrollment
     * at request time. Link materials are not downloaded — the frontend
     * opens external_url directly in a new tab.
     */
    public function downloadMaterial(Request $request, ClassMaterial $material): StreamedResponse|JsonResponse
    {
        $this->service->ensureStudentCanAccessMaterial($request->user(), $material);

        if ($material->type !== 'file' || ! $material->file_path) {
            return $this->error(
                message: 'This material is not a downloadable file.',
                errorCode: 'VALIDATION_ERROR',
                status: 422,
            );
        }

        $disk = config('filesystems.default');

        if (! Storage::disk($disk)->exists($material->file_path)) {
            return $this->error(
                message: 'File no longer exists.',
                errorCode: 'NOT_FOUND',
                status: 404,
            );
        }

        return Storage::disk($disk)->download(
            $material->file_path,
            $material->file_name,
            ['Content-Type' => $material->mime_type ?? 'application/octet-stream'],
        );
    }

    /**
     * Student's own attendance records + overall stats.
     */
    public function myAttendance(Request $request): JsonResponse
    {
        return $this->success(
            data: $this->attendanceService->getStudentAttendance($request->user()),
            message: 'Attendance retrieved',
        );
    }

    /**
     * Student's monthly attendance breakdown.
     */
    public function monthlyAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return $this->success(
            data: $this->attendanceService->getStudentMonthlyAttendance(
                $request->user(),
                (int) $validated['year'],
                (int) $validated['month'],
            ),
            message: 'Monthly attendance retrieved',
        );
    }
}
