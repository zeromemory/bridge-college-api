<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\MarkAttendanceRequest;
use App\Models\ClassRoom;
use App\Services\AttendanceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AttendanceService $service,
    ) {}

    /**
     * View class attendance for a specific date.
     */
    public function index(Request $request, ClassRoom $class): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        return $this->success(
            data: $this->service->getClassAttendanceForAdmin($class, $validated['date']),
            message: 'Attendance retrieved',
        );
    }

    /**
     * Admin marks/edits attendance for any class.
     */
    public function mark(MarkAttendanceRequest $request, ClassRoom $class): JsonResponse
    {
        try {
            $data = $request->validated();
            $results = $this->service->markAttendanceForAdmin(
                $request->user(),
                $class,
                $data['date'],
                $data['records'],
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
            message: 'Attendance saved',
            status: 201,
        );
    }

    /**
     * Monthly summary with per-student attendance stats.
     */
    public function monthly(Request $request, ClassRoom $class): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return $this->success(
            data: $this->service->getClassMonthlySummaryForAdmin(
                $class,
                (int) $validated['year'],
                (int) $validated['month'],
            ),
            message: 'Monthly summary retrieved',
        );
    }
}
