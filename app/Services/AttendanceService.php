<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    public function __construct(
        private readonly TeacherDashboardService $teacherDashboard,
    ) {}

    /**
     * Bulk mark/update attendance for a class on a given date.
     * Uses updateOrCreate in a transaction for idempotent upsert.
     *
     * @throws AuthorizationException
     * @throws \InvalidArgumentException
     */
    public function markAttendance(User $teacher, ClassRoom $class, string $date, array $records): array
    {
        $this->teacherDashboard->ensureTeacherCanAccessClass($teacher, $class);

        return $this->upsertAttendance($teacher, $class, $date, $records);
    }

    /**
     * Admin can mark/edit attendance for any class — no access check.
     */
    public function markAttendanceForAdmin(User $admin, ClassRoom $class, string $date, array $records): array
    {
        return $this->upsertAttendance($admin, $class, $date, $records);
    }

    /**
     * Get class attendance for a specific date. Returns all enrolled students
     * with their attendance status (null if not yet marked).
     *
     * @throws AuthorizationException
     */
    public function getClassAttendance(User $teacher, ClassRoom $class, string $date): array
    {
        $this->teacherDashboard->ensureTeacherCanAccessClass($teacher, $class);

        return $this->buildClassAttendance($class, $date);
    }

    /**
     * Admin version — no access check, includes who marked each record.
     */
    public function getClassAttendanceForAdmin(ClassRoom $class, string $date): array
    {
        return $this->buildClassAttendance($class, $date, includeMarkedBy: true);
    }

    /**
     * Monthly history: which dates have been marked and summary per date.
     *
     * @throws AuthorizationException
     */
    public function getClassAttendanceHistory(User $teacher, ClassRoom $class, int $year, int $month): array
    {
        $this->teacherDashboard->ensureTeacherCanAccessClass($teacher, $class);

        return $this->buildMonthlyHistory($class, $year, $month);
    }

    /**
     * Admin monthly summary with per-student stats.
     */
    public function getClassMonthlySummaryForAdmin(ClassRoom $class, int $year, int $month): array
    {
        $history = $this->buildMonthlyHistory($class, $year, $month);

        // Per-student monthly stats
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $studentStats = Attendance::where('class_id', $class->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select('student_id')
            ->selectRaw("COUNT(*) as total_days")
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late")
            ->selectRaw("SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as `leave`")
            ->groupBy('student_id')
            ->get();

        $studentIds = $studentStats->pluck('student_id');
        $students = User::whereIn('id', $studentIds)
            ->select('id', 'name', 'cnic')
            ->get()
            ->keyBy('id');

        $perStudent = $studentStats->map(function ($stat) use ($students) {
            $total = (int) $stat->total_days;
            $present = (int) $stat->present;
            $late = (int) $stat->late;
            $percentage = $total > 0 ? round(($present + $late) / $total * 100, 1) : 0;

            return [
                'student' => $students->get($stat->student_id),
                'total_days' => $total,
                'present' => $present,
                'absent' => (int) $stat->absent,
                'late' => $late,
                'leave' => (int) $stat->leave,
                'percentage' => $percentage,
            ];
        })->values();

        return [
            'daily_summary' => $history['daily_summary'],
            'student_stats' => $perStudent,
        ];
    }

    /**
     * Student's own attendance records + overall stats.
     */
    public function getStudentAttendance(User $student): ?array
    {
        $enrollment = $this->findActiveEnrollment($student);

        if (! $enrollment) {
            return null;
        }

        $records = Attendance::where('student_id', $student->id)
            ->where('class_id', $enrollment->class_id)
            ->orderByDesc('date')
            ->get();

        $stats = $this->calculateStudentStats($records);

        return [
            'class_id' => $enrollment->class_id,
            'stats' => $stats,
            'records' => $records->map(fn (Attendance $a) => [
                'date' => $a->date->format('Y-m-d'),
                'status' => $a->status,
                'remarks' => $a->remarks,
            ])->values(),
        ];
    }

    /**
     * Student's monthly attendance breakdown.
     */
    public function getStudentMonthlyAttendance(User $student, int $year, int $month): ?array
    {
        $enrollment = $this->findActiveEnrollment($student);

        if (! $enrollment) {
            return null;
        }

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $records = Attendance::where('student_id', $student->id)
            ->where('class_id', $enrollment->class_id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->get();

        $stats = $this->calculateStudentStats($records);

        return [
            'class_id' => $enrollment->class_id,
            'year' => $year,
            'month' => $month,
            'stats' => $stats,
            'records' => $records->map(fn (Attendance $a) => [
                'date' => $a->date->format('Y-m-d'),
                'day_name' => $a->date->format('l'),
                'status' => $a->status,
                'remarks' => $a->remarks,
            ])->values(),
        ];
    }

    /**
     * Shared upsert logic for both teacher and admin marking.
     */
    private function upsertAttendance(User $marker, ClassRoom $class, string $date, array $records): array
    {
        // Get active enrolled student IDs for this class
        $enrolledIds = $class->enrollments()
            ->where('status', 'active')
            ->pluck('student_id')
            ->toArray();

        // Validate all student IDs are enrolled
        $requestedIds = array_column($records, 'student_id');
        $invalid = array_diff($requestedIds, $enrolledIds);

        if (! empty($invalid)) {
            throw new \InvalidArgumentException(
                'Some students are not enrolled in this class: ' . implode(', ', $invalid)
            );
        }

        $results = DB::transaction(function () use ($marker, $class, $date, $records) {
            $upserted = [];
            foreach ($records as $record) {
                $upserted[] = Attendance::updateOrCreate(
                    [
                        'class_id' => $class->id,
                        'student_id' => $record['student_id'],
                        'date' => $date,
                    ],
                    [
                        'marked_by' => $marker->id,
                        'status' => $record['status'],
                        'remarks' => $record['remarks'] ?? null,
                    ]
                );
            }

            return $upserted;
        });

        Log::info('Attendance marked', [
            'class_id' => $class->id,
            'marked_by' => $marker->id,
            'date' => $date,
            'count' => count($results),
        ]);

        return $results;
    }

    /**
     * Build class attendance for a date — all enrolled students with status.
     */
    private function buildClassAttendance(ClassRoom $class, string $date, bool $includeMarkedBy = false): array
    {
        $enrollments = $class->enrollments()
            ->where('status', 'active')
            ->with('student:id,name,cnic')
            ->orderBy('enrolled_at')
            ->get();

        $attendanceMap = Attendance::where('class_id', $class->id)
            ->where('date', $date)
            ->when($includeMarkedBy, fn ($q) => $q->with('markedByUser:id,name'))
            ->get()
            ->keyBy('student_id');

        $students = $enrollments->map(function ($enrollment) use ($attendanceMap, $includeMarkedBy) {
            $attendance = $attendanceMap->get($enrollment->student_id);
            $item = [
                'student_id' => $enrollment->student_id,
                'student' => $enrollment->student,
                'status' => $attendance?->status,
                'remarks' => $attendance?->remarks,
            ];

            if ($includeMarkedBy && $attendance) {
                $item['marked_by'] = $attendance->markedByUser;
            }

            return $item;
        });

        $statusCounts = $students->pluck('status')->filter()->countBy();

        return [
            'date' => $date,
            'students' => $students,
            'summary' => [
                'total' => $students->count(),
                'marked' => $statusCounts->sum(),
                'present' => $statusCounts->get('present', 0),
                'absent' => $statusCounts->get('absent', 0),
                'late' => $statusCounts->get('late', 0),
                'leave' => $statusCounts->get('leave', 0),
            ],
        ];
    }

    /**
     * Build monthly history: which dates were marked + summary per date.
     */
    private function buildMonthlyHistory(ClassRoom $class, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $dailySummary = Attendance::where('class_id', $class->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select('date')
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late")
            ->selectRaw("SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as `leave`")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'total' => (int) $row->total,
                'present' => (int) $row->present,
                'absent' => (int) $row->absent,
                'late' => (int) $row->late,
                'leave' => (int) $row->leave,
            ]);

        return [
            'year' => $year,
            'month' => $month,
            'daily_summary' => $dailySummary,
        ];
    }

    /**
     * Calculate stats from a collection of attendance records.
     */
    private function calculateStudentStats($records): array
    {
        $total = $records->count();
        $counts = $records->countBy('status');
        $present = $counts->get('present', 0);
        $absent = $counts->get('absent', 0);
        $late = $counts->get('late', 0);
        $leave = $counts->get('leave', 0);
        $percentage = $total > 0 ? round(($present + $late) / $total * 100, 1) : 0;

        return [
            'total_days' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'leave' => $leave,
            'percentage' => $percentage,
        ];
    }

    private function findActiveEnrollment(User $student): ?Enrollment
    {
        return Enrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->first();
    }
}
