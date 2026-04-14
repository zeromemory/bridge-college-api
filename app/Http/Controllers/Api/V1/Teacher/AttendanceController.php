<?php

namespace App\Http\Controllers\Api\V1\Teacher;

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
     * Mark or update attendance for a class on a date.
     */
    public function mark(MarkAttendanceRequest $request, ClassRoom $class): JsonResponse
    {
        try {
            $data = $request->validated();
            $results = $this->service->markAttendance(
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
     * Get class attendance for a specific date.
     */
    public function index(Request $request, ClassRoom $class): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        return $this->success(
            data: $this->service->getClassAttendance(
                $request->user(),
                $class,
                $validated['date'],
            ),
            message: 'Attendance retrieved',
        );
    }

    /**
     * Monthly attendance history (which dates marked + summary per date).
     */
    public function history(Request $request, ClassRoom $class): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return $this->success(
            data: $this->service->getClassAttendanceHistory(
                $request->user(),
                $class,
                (int) $validated['year'],
                (int) $validated['month'],
            ),
            message: 'Attendance history retrieved',
        );
    }
}
