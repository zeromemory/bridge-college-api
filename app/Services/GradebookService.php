<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentMark;
use App\Models\ClassRoom;
use App\Models\ClassSubjectTeacher;
use App\Models\Enrollment;
use App\Models\GradeScale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GradebookService
{
    // ── Teacher Methods ──

    /**
     * Create a new assessment record for a class+subject.
     *
     * @throws AuthorizationException
     */
    public function createAssessment(User $teacher, ClassRoom $class, array $data): Assessment
    {
        $this->ensureTeacherTeachesSubjectInClass($teacher, $class->id, $data['subject_id']);

        $assessment = Assessment::create([
            'class_id' => $class->id,
            'subject_id' => $data['subject_id'],
            'teacher_id' => $teacher->id,
            'title' => $data['title'],
            'type' => $data['type'],
            'total_marks' => $data['total_marks'],
            'date' => $data['date'],
            'description' => $data['description'] ?? null,
            'is_published' => false,
        ]);

        Log::info('Assessment created', [
            'assessment_id' => $assessment->id,
            'class_id' => $class->id,
            'subject_id' => $data['subject_id'],
            'teacher_id' => $teacher->id,
        ]);

        return $assessment->load('subject:id,name,code');
    }

    /**
     * Update assessment metadata. Rejects total_marks reduction below existing max marks.
     *
     * @throws AuthorizationException
     * @throws \InvalidArgumentException
     */
    public function updateAssessment(User $teacher, Assessment $assessment, array $data): Assessment
    {
        $this->ensureTeacherTeachesSubjectInClass($teacher, $assessment->class_id, $assessment->subject_id);

        // Guard: if total_marks is being lowered and marks exist, check max
        if (isset($data['total_marks']) && (float) $data['total_marks'] < (float) $assessment->total_marks) {
            $maxMarks = $assessment->marks()->max('marks_obtained');
            if ($maxMarks !== null && (float) $data['total_marks'] < (float) $maxMarks) {
                throw new \InvalidArgumentException(
                    "Cannot reduce total marks below the highest entered mark ({$maxMarks})."
                );
            }
        }

        $assessment->update($data);

        Log::info('Assessment updated', [
            'assessment_id' => $assessment->id,
            'teacher_id' => $teacher->id,
        ]);

        return $assessment->fresh()->load('subject:id,name,code');
    }

    /**
     * Soft-delete an unpublished assessment. Published assessments cannot be deleted.
     *
     * @throws AuthorizationException
     * @throws \InvalidArgumentException
     */
    public function deleteAssessment(User $teacher, Assessment $assessment): void
    {
        $this->ensureTeacherTeachesSubjectInClass($teacher, $assessment->class_id, $assessment->subject_id);

        if ($assessment->is_published) {
            throw new \InvalidArgumentException('Cannot delete a published assessment. Unpublish it first.');
        }

        $assessment->delete();

        Log::info('Assessment soft-deleted', [
            'assessment_id' => $assessment->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    /**
     * Publish an assessment — makes it visible to students.
     *
     * @throws AuthorizationException
     */
    public function publishAssessment(User $teacher, Assessment $assessment): Assessment
    {
        $this->ensureTeacherTeachesSubjectInClass($teacher, $assessment->class_id, $assessment->subject_id);

        $assessment->update(['is_published' => true]);

        Log::info('Assessment published', [
            'assessment_id' => $assessment->id,
            'teacher_id' => $teacher->id,
        ]);

        return $assessment;
    }

    /**
     * Unpublish an assessment — hides it from students.
     *
     * @throws AuthorizationException
     */
    public function unpublishAssessment(User $teacher, Assessment $assessment): Assessment
    {
        $this->ensureTeacherTeachesSubjectInClass($teacher, $assessment->class_id, $assessment->subject_id);

        $assessment->update(['is_published' => false]);

        Log::info('Assessment unpublished', [
            'assessment_id' => $assessment->id,
            'teacher_id' => $teacher->id,
        ]);

        return $assessment;
    }

    /**
     * Bulk save/update marks for an assessment.
     * Validates: student enrolled, marks <= total_marks.
     *
     * @throws AuthorizationException
     * @throws \InvalidArgumentException
     */
    public function saveMarks(User $teacher, Assessment $assessment, array $marks): array
    {
        $this->ensureTeacherTeachesSubjectInClass($teacher, $assessment->class_id, $assessment->subject_id);

        // Get enrolled student IDs
        $enrolledIds = Enrollment::where('class_id', $assessment->class_id)
            ->where('status', 'active')
            ->pluck('student_id')
            ->toArray();

        // Validate all students are enrolled and marks are within range
        foreach ($marks as $entry) {
            if (! in_array($entry['student_id'], $enrolledIds)) {
                throw new \InvalidArgumentException(
                    "Student {$entry['student_id']} is not enrolled in this class."
                );
            }

            if (isset($entry['marks_obtained']) && $entry['marks_obtained'] !== null) {
                if ((float) $entry['marks_obtained'] > (float) $assessment->total_marks) {
                    throw new \InvalidArgumentException(
                        "Marks obtained ({$entry['marks_obtained']}) cannot exceed total marks ({$assessment->total_marks})."
                    );
                }
            }
        }

        $results = DB::transaction(function () use ($assessment, $marks) {
            $upserted = [];
            foreach ($marks as $entry) {
                $upserted[] = AssessmentMark::updateOrCreate(
                    [
                        'assessment_id' => $assessment->id,
                        'student_id' => $entry['student_id'],
                    ],
                    [
                        'marks_obtained' => $entry['marks_obtained'] ?? null,
                        'is_absent' => $entry['is_absent'] ?? false,
                        'remarks' => $entry['remarks'] ?? null,
                    ]
                );
            }

            return $upserted;
        }, 3);

        Log::info('Assessment marks saved', [
            'assessment_id' => $assessment->id,
            'teacher_id' => $teacher->id,
            'count' => count($results),
        ]);

        return $results;
    }

    /**
     * Get assessment detail with ALL enrolled students (left-join pattern).
     * Students without marks show as marks_obtained: null, is_absent: false.
     *
     * @throws AuthorizationException
     */
    public function getAssessmentWithMarks(User $teacher, Assessment $assessment): array
    {
        $this->ensureTeacherTeachesSubjectInClass($teacher, $assessment->class_id, $assessment->subject_id);

        $assessment->load('subject:id,name,code');

        // Get all enrolled students
        $enrollments = Enrollment::where('class_id', $assessment->class_id)
            ->where('status', 'active')
            ->with('student:id,name,cnic')
            ->orderBy('enrolled_at')
            ->get();

        // Get existing marks keyed by student_id
        $marksMap = $assessment->marks->keyBy('student_id');

        $students = $enrollments->map(function ($enrollment) use ($marksMap, $assessment) {
            $mark = $marksMap->get($enrollment->student_id);
            $marksObtained = $mark?->marks_obtained;
            $percentage = null;
            $grade = null;
            $gradeRemarks = null;

            if ($marksObtained !== null) {
                $percentage = $this->calculatePercentage((float) $marksObtained, (float) $assessment->total_marks);
                $gradeInfo = $this->calculateGrade($percentage);
                $grade = $gradeInfo['grade'];
                $gradeRemarks = $gradeInfo['remarks'];
            }

            return [
                'student_id' => $enrollment->student_id,
                'student' => $enrollment->student,
                'marks_obtained' => $marksObtained !== null ? (float) $marksObtained : null,
                'is_absent' => $mark?->is_absent ?? false,
                'remarks' => $mark?->remarks,
                'percentage' => $percentage,
                'grade' => $grade,
                'grade_remarks' => $gradeRemarks,
            ];
        });

        return [
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'type' => $assessment->type,
                'subject' => $assessment->subject,
                'total_marks' => (float) $assessment->total_marks,
                'date' => $assessment->date->format('Y-m-d'),
                'description' => $assessment->description,
                'is_published' => $assessment->is_published,
            ],
            'students' => $students,
        ];
    }

    /**
     * List assessments for a class with optional subject/type filters.
     * Includes marks_entered count vs enrolled_count.
     *
     * @throws AuthorizationException
     */
    public function getClassAssessments(User $teacher, ClassRoom $class, ?int $subjectId = null, ?string $type = null): array
    {
        $this->ensureTeacherTeachesSubjectInClass($teacher, $class->id, $subjectId);

        $query = Assessment::where('class_id', $class->id)
            ->with('subject:id,name,code')
            ->withCount(['marks as marks_count' => function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNotNull('marks_obtained')->orWhere('is_absent', true);
                });
            }]);

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        } else {
            // Only show assessments for subjects this teacher teaches in this class
            $teacherSubjectIds = ClassSubjectTeacher::where('class_id', $class->id)
                ->where('teacher_id', $teacher->id)
                ->pluck('subject_id');
            $query->whereIn('subject_id', $teacherSubjectIds);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $assessments = $query->orderByDesc('date')->orderByDesc('id')->get();

        $enrolledCount = Enrollment::where('class_id', $class->id)
            ->where('status', 'active')
            ->count();

        return [
            'assessments' => $assessments->map(fn (Assessment $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'type' => $a->type,
                'subject' => $a->subject,
                'total_marks' => (float) $a->total_marks,
                'date' => $a->date->format('Y-m-d'),
                'is_published' => $a->is_published,
                'marks_entered' => $a->marks_count,
                'enrolled_count' => $enrolledCount,
            ]),
        ];
    }

    /**
     * Grade summary for a single student in a class (teacher view).
     *
     * @throws AuthorizationException
     * @throws \InvalidArgumentException
     */
    public function getStudentGradeSummary(User $teacher, ClassRoom $class, int $studentId): array
    {
        // Verify teacher has access to this class
        $teacherSubjectIds = ClassSubjectTeacher::where('class_id', $class->id)
            ->where('teacher_id', $teacher->id)
            ->pluck('subject_id');

        if ($teacherSubjectIds->isEmpty()) {
            throw new AuthorizationException('You do not teach any subjects in this class.');
        }

        // Verify student is enrolled
        $enrollment = Enrollment::where('class_id', $class->id)
            ->where('student_id', $studentId)
            ->first();

        if (! $enrollment) {
            throw new \InvalidArgumentException('Student is not enrolled in this class.');
        }

        return $this->buildStudentGrades($studentId, $class->id, $teacherSubjectIds->toArray());
    }

    // ── Student Methods ──

    /**
     * All published grades for the authenticated student, grouped by subject.
     */
    public function getMyGrades(User $student): ?array
    {
        $enrollment = $this->findActiveEnrollment($student);

        if (! $enrollment) {
            return null;
        }

        return $this->buildStudentGradesForStudent($student->id, $enrollment);
    }

    /**
     * Published grades filtered by assessment type.
     */
    public function getMyGradesByType(User $student, string $type): ?array
    {
        $enrollment = $this->findActiveEnrollment($student);

        if (! $enrollment) {
            return null;
        }

        return $this->buildStudentGradesForStudent($student->id, $enrollment, $type);
    }

    // ── Admin Methods ──

    /**
     * Class-wise results: per-student totals across all published assessments.
     */
    public function getClassResults(ClassRoom $class, ?int $subjectId = null, ?string $type = null): array
    {
        $query = Assessment::where('class_id', $class->id)
            ->where('is_published', true);

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }
        if ($type) {
            $query->where('type', $type);
        }

        $assessments = $query->get();
        $assessmentIds = $assessments->pluck('id');

        // Single query: get all marks with assessment total_marks via join
        $assessmentTotals = $assessments->keyBy('id');
        $rawMarks = AssessmentMark::whereIn('assessment_id', $assessmentIds)
            ->whereNotNull('marks_obtained')
            ->get()
            ->groupBy('student_id');

        $studentIds = $rawMarks->keys();
        $students = User::whereIn('id', $studentIds)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        $results = $rawMarks->map(function ($marks, $studentId) use ($students, $assessmentTotals) {
            $totalObtained = 0;
            $totalMarks = 0;

            foreach ($marks as $mark) {
                $totalObtained += (float) $mark->marks_obtained;
                $assessment = $assessmentTotals->get($mark->assessment_id);
                if ($assessment) {
                    $totalMarks += (float) $assessment->total_marks;
                }
            }

            $percentage = $totalMarks > 0 ? $this->calculatePercentage($totalObtained, $totalMarks) : 0;
            $gradeInfo = $this->calculateGrade($percentage);

            return [
                'student' => $students->get($studentId),
                'total_marks' => $totalMarks,
                'marks_obtained' => $totalObtained,
                'percentage' => $percentage,
                'grade' => $gradeInfo['grade'],
                'grade_remarks' => $gradeInfo['remarks'],
                'assessments_count' => $marks->count(),
            ];
        })->sortByDesc('percentage')->values();

        return [
            'class' => ['id' => $class->id, 'name' => $class->name],
            'students' => $results,
        ];
    }

    /**
     * Subject-wise results: per-student marks for all assessments in a subject.
     */
    public function getSubjectResults(ClassRoom $class, int $subjectId): array
    {
        $assessments = Assessment::where('class_id', $class->id)
            ->where('subject_id', $subjectId)
            ->where('is_published', true)
            ->with('subject:id,name,code')
            ->orderBy('date')
            ->get();

        $assessmentIds = $assessments->pluck('id');

        $marks = AssessmentMark::whereIn('assessment_id', $assessmentIds)
            ->get()
            ->groupBy('student_id');

        $studentIds = $marks->keys();
        $students = User::whereIn('id', $studentIds)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        $results = $marks->map(function ($studentMarks, $studentId) use ($students, $assessments) {
            $assessmentResults = [];
            $totalObtained = 0;
            $totalMarks = 0;

            foreach ($assessments as $assessment) {
                $mark = $studentMarks->firstWhere('assessment_id', $assessment->id);
                $obtained = $mark?->marks_obtained;

                $assessmentResult = [
                    'assessment_id' => $assessment->id,
                    'title' => $assessment->title,
                    'type' => $assessment->type,
                    'total_marks' => (float) $assessment->total_marks,
                    'marks_obtained' => $obtained !== null ? (float) $obtained : null,
                    'is_absent' => $mark?->is_absent ?? false,
                ];

                if ($obtained !== null) {
                    $pct = $this->calculatePercentage((float) $obtained, (float) $assessment->total_marks);
                    $gi = $this->calculateGrade($pct);
                    $assessmentResult['percentage'] = $pct;
                    $assessmentResult['grade'] = $gi['grade'];
                    $totalObtained += (float) $obtained;
                    $totalMarks += (float) $assessment->total_marks;
                }

                $assessmentResults[] = $assessmentResult;
            }

            $overallPct = $totalMarks > 0 ? $this->calculatePercentage($totalObtained, $totalMarks) : 0;
            $overallGrade = $this->calculateGrade($overallPct);

            return [
                'student' => $students->get($studentId),
                'assessments' => $assessmentResults,
                'summary' => [
                    'total_marks' => $totalMarks,
                    'marks_obtained' => $totalObtained,
                    'percentage' => $overallPct,
                    'grade' => $overallGrade['grade'],
                    'grade_remarks' => $overallGrade['remarks'],
                ],
            ];
        })->values();

        $subject = $assessments->first()?->subject;

        return [
            'class' => ['id' => $class->id, 'name' => $class->name],
            'subject' => $subject,
            'assessments' => $assessments->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'type' => $a->type,
                'total_marks' => (float) $a->total_marks,
                'date' => $a->date->format('Y-m-d'),
            ])->values(),
            'students' => $results,
        ];
    }

    /**
     * Student-wise results: all results across all classes for one student.
     */
    public function getStudentResults(int $studentId): array
    {
        $student = User::select('id', 'name')->findOrFail($studentId);

        $enrollments = Enrollment::where('student_id', $studentId)
            ->with('classRoom:id,name')
            ->get();

        $classIds = $enrollments->pluck('class_id');

        // Batch: load all published assessments across all enrolled classes
        $allAssessments = Assessment::whereIn('class_id', $classIds)
            ->where('is_published', true)
            ->with('subject:id,name,code')
            ->orderBy('date')
            ->get()
            ->groupBy('class_id');

        // Batch: load all marks for this student across all assessments
        $allAssessmentIds = $allAssessments->flatten()->pluck('id');
        $allMarks = AssessmentMark::where('student_id', $studentId)
            ->whereIn('assessment_id', $allAssessmentIds)
            ->get()
            ->keyBy('assessment_id');

        $classes = $enrollments->map(function ($enrollment) use ($allAssessments, $allMarks) {
            $classAssessments = $allAssessments->get($enrollment->class_id, collect());
            $result = $this->buildGradesFromPreloaded($classAssessments, $allMarks);
            $result['class'] = ['id' => $enrollment->classRoom->id, 'name' => $enrollment->classRoom->name];

            return $result;
        })->values()->toArray();

        return [
            'student' => $student,
            'classes' => $classes,
        ];
    }

    // ── Private Helpers ──

    private function calculatePercentage(float $obtained, float $total): float
    {
        if ($total <= 0) {
            return 0;
        }

        return round(($obtained / $total) * 100, 2);
    }

    private function calculateGrade(float $percentage): array
    {
        $scales = Cache::remember('grade_scales', 3600, function () {
            return GradeScale::orderBy('sort_order')->get();
        });

        foreach ($scales as $scale) {
            if ($percentage >= (float) $scale->min_percentage && $percentage <= (float) $scale->max_percentage) {
                return [
                    'grade' => $scale->grade,
                    'remarks' => $scale->remarks,
                ];
            }
        }

        // Fallback — should never happen with proper scale data
        return ['grade' => 'F', 'remarks' => 'Fail'];
    }

    /**
     * Verify teacher teaches a specific subject in a class.
     * If subjectId is null, verifies teacher teaches ANY subject in the class.
     *
     * @throws AuthorizationException
     */
    private function ensureTeacherTeachesSubjectInClass(User $teacher, int $classId, ?int $subjectId = null): void
    {
        $query = ClassSubjectTeacher::where('class_id', $classId)
            ->where('teacher_id', $teacher->id);

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        if (! $query->exists()) {
            throw new AuthorizationException('You do not teach this subject in this class.');
        }
    }

    private function findActiveEnrollment(User $student): ?Enrollment
    {
        return Enrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Build student grades for teacher view (only teacher's subjects).
     */
    private function buildStudentGrades(int $studentId, int $classId, array $subjectIds): array
    {
        return $this->buildStudentGradesAllSubjects($studentId, $classId, $subjectIds, publishedOnly: false);
    }

    /**
     * Build student grades for student view (all subjects, published only).
     */
    private function buildStudentGradesForStudent(int $studentId, Enrollment $enrollment, ?string $type = null): array
    {
        $classId = $enrollment->class_id;
        $class = ClassRoom::select('id', 'name')->find($classId);

        $result = $this->buildStudentGradesAllSubjects($studentId, $classId, type: $type, publishedOnly: true);
        $result['class'] = $class ? ['id' => $class->id, 'name' => $class->name] : null;

        return $result;
    }

    /**
     * Core grade-building logic shared by teacher, student, and admin views.
     */
    private function buildStudentGradesAllSubjects(
        int $studentId,
        int $classId,
        ?array $subjectIds = null,
        ?string $type = null,
        bool $publishedOnly = true,
    ): array {
        $query = Assessment::where('class_id', $classId);

        if ($publishedOnly) {
            $query->where('is_published', true);
        }

        if ($subjectIds) {
            $query->whereIn('subject_id', $subjectIds);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $assessments = $query->with('subject:id,name,code')->orderBy('date')->get();

        // Get marks for this student
        $marks = AssessmentMark::where('student_id', $studentId)
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->keyBy('assessment_id');

        // Group by subject
        $bySubject = $assessments->groupBy('subject_id');

        $overallTotalMarks = 0;
        $overallObtained = 0;

        $subjects = $bySubject->map(function ($subjectAssessments) use ($marks, &$overallTotalMarks, &$overallObtained) {
            $subject = $subjectAssessments->first()->subject;
            $subjectTotal = 0;
            $subjectObtained = 0;

            $assessmentList = $subjectAssessments->map(function ($assessment) use ($marks, &$subjectTotal, &$subjectObtained) {
                $mark = $marks->get($assessment->id);
                $obtained = $mark?->marks_obtained;

                $result = [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'type' => $assessment->type,
                    'date' => $assessment->date->format('Y-m-d'),
                    'total_marks' => (float) $assessment->total_marks,
                    'marks_obtained' => $obtained !== null ? (float) $obtained : null,
                    'is_absent' => $mark?->is_absent ?? false,
                ];

                if ($obtained !== null) {
                    $pct = $this->calculatePercentage((float) $obtained, (float) $assessment->total_marks);
                    $gi = $this->calculateGrade($pct);
                    $result['percentage'] = $pct;
                    $result['grade'] = $gi['grade'];
                    $result['grade_remarks'] = $gi['remarks'];

                    $subjectTotal += (float) $assessment->total_marks;
                    $subjectObtained += (float) $obtained;
                } else {
                    $result['percentage'] = null;
                    $result['grade'] = null;
                    $result['grade_remarks'] = null;
                }

                return $result;
            })->values();

            $subjectPct = $subjectTotal > 0 ? $this->calculatePercentage($subjectObtained, $subjectTotal) : 0;
            $subjectGrade = $this->calculateGrade($subjectPct);

            $overallTotalMarks += $subjectTotal;
            $overallObtained += $subjectObtained;

            return [
                'subject' => $subject,
                'assessments' => $assessmentList,
                'summary' => [
                    'total_marks' => $subjectTotal,
                    'marks_obtained' => $subjectObtained,
                    'percentage' => $subjectPct,
                    'grade' => $subjectGrade['grade'],
                    'grade_remarks' => $subjectGrade['remarks'],
                ],
            ];
        })->values();

        $overallPct = $overallTotalMarks > 0 ? $this->calculatePercentage($overallObtained, $overallTotalMarks) : 0;
        $overallGrade = $this->calculateGrade($overallPct);

        return [
            'subjects' => $subjects,
            'overall' => [
                'total_marks' => $overallTotalMarks,
                'marks_obtained' => $overallObtained,
                'percentage' => $overallPct,
                'grade' => $overallGrade['grade'],
                'grade_remarks' => $overallGrade['remarks'],
            ],
        ];
    }

    /**
     * Build grades from pre-loaded assessments and marks (avoids N+1 in getStudentResults).
     */
    private function buildGradesFromPreloaded(Collection $assessments, Collection $marksMap): array
    {
        $bySubject = $assessments->groupBy('subject_id');
        $overallTotalMarks = 0;
        $overallObtained = 0;

        $subjects = $bySubject->map(function ($subjectAssessments) use ($marksMap, &$overallTotalMarks, &$overallObtained) {
            $subject = $subjectAssessments->first()->subject;
            $subjectTotal = 0;
            $subjectObtained = 0;

            $assessmentList = $subjectAssessments->map(function ($assessment) use ($marksMap, &$subjectTotal, &$subjectObtained) {
                $mark = $marksMap->get($assessment->id);
                $obtained = $mark?->marks_obtained;

                $result = [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'type' => $assessment->type,
                    'date' => $assessment->date->format('Y-m-d'),
                    'total_marks' => (float) $assessment->total_marks,
                    'marks_obtained' => $obtained !== null ? (float) $obtained : null,
                    'is_absent' => $mark?->is_absent ?? false,
                    'percentage' => null,
                    'grade' => null,
                    'grade_remarks' => null,
                ];

                if ($obtained !== null) {
                    $pct = $this->calculatePercentage((float) $obtained, (float) $assessment->total_marks);
                    $gi = $this->calculateGrade($pct);
                    $result['percentage'] = $pct;
                    $result['grade'] = $gi['grade'];
                    $result['grade_remarks'] = $gi['remarks'];
                    $subjectTotal += (float) $assessment->total_marks;
                    $subjectObtained += (float) $obtained;
                }

                return $result;
            })->values();

            $subjectPct = $subjectTotal > 0 ? $this->calculatePercentage($subjectObtained, $subjectTotal) : 0;
            $subjectGrade = $this->calculateGrade($subjectPct);
            $overallTotalMarks += $subjectTotal;
            $overallObtained += $subjectObtained;

            return [
                'subject' => $subject,
                'assessments' => $assessmentList,
                'summary' => [
                    'total_marks' => $subjectTotal,
                    'marks_obtained' => $subjectObtained,
                    'percentage' => $subjectPct,
                    'grade' => $subjectGrade['grade'],
                    'grade_remarks' => $subjectGrade['remarks'],
                ],
            ];
        })->values();

        $overallPct = $overallTotalMarks > 0 ? $this->calculatePercentage($overallObtained, $overallTotalMarks) : 0;
        $overallGrade = $this->calculateGrade($overallPct);

        return [
            'subjects' => $subjects,
            'overall' => [
                'total_marks' => $overallTotalMarks,
                'marks_obtained' => $overallObtained,
                'percentage' => $overallPct,
                'grade' => $overallGrade['grade'],
                'grade_remarks' => $overallGrade['remarks'],
            ],
        ];
    }
}
