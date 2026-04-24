<?php

use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AssessmentMark;
use App\Models\Branch;
use App\Models\ClassRoom;
use App\Models\ClassSubjectTeacher;
use App\Models\Enrollment;
use App\Models\GradeScale;
use App\Models\Program;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->student = User::factory()->create();
    $this->program = Program::factory()->create();
    $this->branch = Branch::factory()->create();
    $this->session = AcademicSession::factory()->active()->create();
    $this->subject = Subject::factory()->create();

    $this->class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
        'class_teacher_id' => $this->teacher->id,
    ]);

    ClassSubjectTeacher::create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
    ]);

    Enrollment::create([
        'student_id' => $this->student->id,
        'class_id' => $this->class->id,
        'enrolled_at' => now(),
        'status' => 'active',
    ]);

    // Seed grade scales
    $scales = [
        ['grade' => 'A+', 'min_percentage' => 90.00, 'max_percentage' => 100.00, 'remarks' => 'Exceptional', 'sort_order' => 1],
        ['grade' => 'A', 'min_percentage' => 80.00, 'max_percentage' => 89.99, 'remarks' => 'Excellent', 'sort_order' => 2],
        ['grade' => 'B', 'min_percentage' => 70.00, 'max_percentage' => 79.99, 'remarks' => 'Very Good', 'sort_order' => 3],
        ['grade' => 'C', 'min_percentage' => 60.00, 'max_percentage' => 69.99, 'remarks' => 'Good', 'sort_order' => 4],
        ['grade' => 'D', 'min_percentage' => 50.00, 'max_percentage' => 59.99, 'remarks' => 'Satisfactory', 'sort_order' => 5],
        ['grade' => 'F', 'min_percentage' => 0.00, 'max_percentage' => 49.99, 'remarks' => 'Fail', 'sort_order' => 6],
    ];

    foreach ($scales as $scale) {
        GradeScale::create($scale);
    }
});

// ── Role Guards ──

it('unauthenticated user cannot access teacher gradebook', function () {
    $this->getJson("/api/v1/teacher/classes/{$this->class->id}/assessments")
        ->assertUnauthorized();
});

it('student cannot access teacher gradebook routes', function () {
    $this->actingAs($this->student)
        ->getJson("/api/v1/teacher/classes/{$this->class->id}/assessments")
        ->assertForbidden();
});

it('wrong teacher cannot access another teachers class assessments', function () {
    $otherTeacher = User::factory()->teacher()->create();

    $this->actingAs($otherTeacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/assessments", [
            'subject_id' => $this->subject->id,
            'title' => 'Test',
            'type' => 'class_test',
            'total_marks' => 25,
            'date' => now()->format('Y-m-d'),
        ])->assertForbidden();
});

it('unauthenticated user cannot access student grades', function () {
    $this->getJson('/api/v1/student/lms/grades')
        ->assertUnauthorized();
});

// ── CRUD ──

it('teacher can create an assessment', function () {
    $response = $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/assessments", [
            'subject_id' => $this->subject->id,
            'title' => 'Chapter 1 Test',
            'type' => 'class_test',
            'total_marks' => 25,
            'date' => now()->format('Y-m-d'),
            'description' => 'First chapter test',
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Chapter 1 Test')
        ->assertJsonPath('data.type', 'class_test')
        ->assertJsonPath('data.is_published', false);

    $this->assertDatabaseHas('assessments', [
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'title' => 'Chapter 1 Test',
    ]);
});

it('teacher can update an assessment', function () {
    $assessment = Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 25,
    ]);

    $response = $this->actingAs($this->teacher)
        ->putJson("/api/v1/teacher/assessments/{$assessment->id}", [
            'title' => 'Updated Title',
            'total_marks' => 30,
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Updated Title');

    $this->assertDatabaseHas('assessments', [
        'id' => $assessment->id,
        'title' => 'Updated Title',
        'total_marks' => 30,
    ]);
});

it('cannot delete a published assessment', function () {
    $assessment = Assessment::factory()->published()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
    ]);

    $this->actingAs($this->teacher)
        ->deleteJson("/api/v1/teacher/assessments/{$assessment->id}")
        ->assertUnprocessable();

    $this->assertDatabaseHas('assessments', [
        'id' => $assessment->id,
        'deleted_at' => null,
    ]);
});

it('can delete an unpublished assessment', function () {
    $assessment = Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'is_published' => false,
    ]);

    $this->actingAs($this->teacher)
        ->deleteJson("/api/v1/teacher/assessments/{$assessment->id}")
        ->assertOk();

    $this->assertSoftDeleted('assessments', ['id' => $assessment->id]);
});

// ── Marks ──

it('teacher can save marks for enrolled students', function () {
    $assessment = Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 25,
    ]);

    $response = $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/assessments/{$assessment->id}/marks", [
            'marks' => [
                [
                    'student_id' => $this->student->id,
                    'marks_obtained' => 22.5,
                    'is_absent' => false,
                    'remarks' => 'Good performance',
                ],
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('assessment_marks', [
        'assessment_id' => $assessment->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 22.5,
        'is_absent' => false,
    ]);
});

it('rejects marks exceeding total marks', function () {
    $assessment = Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 25,
    ]);

    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/assessments/{$assessment->id}/marks", [
            'marks' => [
                ['student_id' => $this->student->id, 'marks_obtained' => 26],
            ],
        ])->assertUnprocessable();
});

it('rejects marks for non-enrolled student', function () {
    $otherStudent = User::factory()->create();

    $assessment = Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 25,
    ]);

    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/assessments/{$assessment->id}/marks", [
            'marks' => [
                ['student_id' => $otherStudent->id, 'marks_obtained' => 20],
            ],
        ])->assertUnprocessable();
});

it('can mark a student as absent', function () {
    $assessment = Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 25,
    ]);

    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/assessments/{$assessment->id}/marks", [
            'marks' => [
                ['student_id' => $this->student->id, 'marks_obtained' => null, 'is_absent' => true],
            ],
        ])->assertCreated();

    $this->assertDatabaseHas('assessment_marks', [
        'assessment_id' => $assessment->id,
        'student_id' => $this->student->id,
        'marks_obtained' => null,
        'is_absent' => true,
    ]);
});

// ── Publish / Unpublish ──

it('teacher can publish an assessment', function () {
    $assessment = Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'is_published' => false,
    ]);

    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/assessments/{$assessment->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.is_published', true);

    $this->assertDatabaseHas('assessments', [
        'id' => $assessment->id,
        'is_published' => true,
    ]);
});

it('teacher can unpublish an assessment', function () {
    $assessment = Assessment::factory()->published()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
    ]);

    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/assessments/{$assessment->id}/unpublish")
        ->assertOk()
        ->assertJsonPath('data.is_published', false);
});

it('student sees only published assessments', function () {
    // Create one published, one unpublished
    $published = Assessment::factory()->published()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 25,
    ]);

    Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'is_published' => false,
        'total_marks' => 50,
    ]);

    AssessmentMark::create([
        'assessment_id' => $published->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 20,
        'is_absent' => false,
    ]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/v1/student/lms/grades');

    $response->assertOk()
        ->assertJsonPath('success', true);

    // Should only see published assessment
    $subjects = $response->json('data.subjects');
    $allAssessments = collect($subjects)->flatMap(fn ($s) => $s['assessments']);
    expect($allAssessments)->toHaveCount(1);
    expect($allAssessments->first()['id'])->toBe($published->id);
});

// ── Student ──

it('student can view their grades', function () {
    $assessment = Assessment::factory()->published()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 100,
    ]);

    AssessmentMark::create([
        'assessment_id' => $assessment->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 85,
        'is_absent' => false,
    ]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/v1/student/lms/grades');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.overall.grade', 'A');

    expect((float) $response->json('data.overall.percentage'))->toBe(85.0);
});

it('student can filter grades by type', function () {
    $classTest = Assessment::factory()->published()->classTest()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
    ]);

    $assignment = Assessment::factory()->published()->assignment()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
    ]);

    AssessmentMark::create([
        'assessment_id' => $classTest->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 20,
    ]);

    AssessmentMark::create([
        'assessment_id' => $assignment->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 15,
    ]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/v1/student/lms/grades/class_test');

    $response->assertOk();

    $subjects = $response->json('data.subjects');
    $allAssessments = collect($subjects)->flatMap(fn ($s) => $s['assessments']);
    expect($allAssessments)->toHaveCount(1);
    expect($allAssessments->first()['type'])->toBe('class_test');
});

it('student with no enrollment gets null grades', function () {
    $newStudent = User::factory()->create();

    $response = $this->actingAs($newStudent)
        ->getJson('/api/v1/student/lms/grades');

    $response->assertOk()
        ->assertJsonPath('data', null);
});

// ── Admin ──

it('admin can view class results', function () {
    $assessment = Assessment::factory()->published()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 100,
    ]);

    AssessmentMark::create([
        'assessment_id' => $assessment->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 75,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/admin/classes/{$this->class->id}/results");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.class.id', $this->class->id);

    expect($response->json('data.students'))->toHaveCount(1);
    expect((float) $response->json('data.students.0.percentage'))->toBe(75.0);
    expect($response->json('data.students.0.grade'))->toBe('B');
});

it('admin can view subject results', function () {
    $assessment = Assessment::factory()->published()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 50,
    ]);

    AssessmentMark::create([
        'assessment_id' => $assessment->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 45,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/admin/classes/{$this->class->id}/subjects/{$this->subject->id}/results");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.subject.id', $this->subject->id);

    expect($response->json('data.students'))->toHaveCount(1);
    expect((float) $response->json('data.students.0.summary.percentage'))->toBe(90.0);
    expect($response->json('data.students.0.summary.grade'))->toBe('A+');
});

it('admin can view student results', function () {
    $assessment = Assessment::factory()->published()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 100,
    ]);

    AssessmentMark::create([
        'assessment_id' => $assessment->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 60,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/admin/students/{$this->student->id}/results");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.student.id', $this->student->id);

    expect($response->json('data.classes'))->toHaveCount(1);
    expect((float) $response->json('data.classes.0.overall.percentage'))->toBe(60.0);
    expect($response->json('data.classes.0.overall.grade'))->toBe('C');
});

// ── Grade Calculation Boundaries ──

it('calculates correct grades at FBISE scale boundaries', function () {
    $assessment = Assessment::factory()->published()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 100,
    ]);

    // Test 90% → A+
    AssessmentMark::updateOrCreate(
        ['assessment_id' => $assessment->id, 'student_id' => $this->student->id],
        ['marks_obtained' => 90, 'is_absent' => false]
    );

    $response = $this->actingAs($this->student)
        ->getJson('/api/v1/student/lms/grades');
    expect($response->json('data.overall.grade'))->toBe('A+');

    // Test 100% → A+
    AssessmentMark::where('assessment_id', $assessment->id)
        ->where('student_id', $this->student->id)
        ->update(['marks_obtained' => 100]);
    // Clear cache to ensure fresh grade scale lookup
    \Illuminate\Support\Facades\Cache::forget('grade_scales');
    $response = $this->actingAs($this->student)->getJson('/api/v1/student/lms/grades');
    expect($response->json('data.overall.grade'))->toBe('A+');

    // Test 89.99% → A (just below A+ boundary)
    // Using 89.99 out of 100 to get exact 89.99%
    $assessment->update(['total_marks' => 10000]);
    AssessmentMark::where('assessment_id', $assessment->id)
        ->where('student_id', $this->student->id)
        ->update(['marks_obtained' => 8999]);
    Cache::forget('grade_scales');
    $response = $this->actingAs($this->student)->getJson('/api/v1/student/lms/grades');
    expect($response->json('data.overall.grade'))->toBe('A');

    // Test 0% → F
    AssessmentMark::where('assessment_id', $assessment->id)
        ->where('student_id', $this->student->id)
        ->update(['marks_obtained' => 0]);
    Cache::forget('grade_scales');
    $response = $this->actingAs($this->student)->getJson('/api/v1/student/lms/grades');
    expect($response->json('data.overall.grade'))->toBe('F');
});

// ── total_marks Update Guard ──

it('rejects total_marks reduction below highest existing mark', function () {
    $assessment = Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 50,
    ]);

    AssessmentMark::create([
        'assessment_id' => $assessment->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 45,
    ]);

    $this->actingAs($this->teacher)
        ->putJson("/api/v1/teacher/assessments/{$assessment->id}", [
            'total_marks' => 40, // Below highest mark of 45
        ])->assertUnprocessable();

    // Should still be 50
    $this->assertDatabaseHas('assessments', [
        'id' => $assessment->id,
        'total_marks' => 50,
    ]);
});

it('allows total_marks increase when marks exist', function () {
    $assessment = Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 25,
    ]);

    AssessmentMark::create([
        'assessment_id' => $assessment->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 20,
    ]);

    $this->actingAs($this->teacher)
        ->putJson("/api/v1/teacher/assessments/{$assessment->id}", [
            'total_marks' => 50,
        ])->assertOk();

    $this->assertDatabaseHas('assessments', [
        'id' => $assessment->id,
        'total_marks' => 50,
    ]);
});

// ── Multi-Teacher Isolation ──

it('teacher cannot access assessments for subjects they do not teach', function () {
    $otherTeacher = User::factory()->teacher()->create();
    $otherSubject = Subject::factory()->create();

    // Assign other teacher to a different subject in the same class
    ClassSubjectTeacher::create([
        'class_id' => $this->class->id,
        'subject_id' => $otherSubject->id,
        'teacher_id' => $otherTeacher->id,
    ]);

    // Other teacher tries to create assessment for original teacher's subject
    $this->actingAs($otherTeacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/assessments", [
            'subject_id' => $this->subject->id, // Not their subject
            'title' => 'Sneaky Test',
            'type' => 'class_test',
            'total_marks' => 25,
            'date' => now()->format('Y-m-d'),
        ])->assertForbidden();
});

// ── Assessment Show (marks entry view) ──

it('teacher can view assessment with all enrolled students', function () {
    $assessment = Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 25,
    ]);

    // Only mark one student, but another is also enrolled
    $student2 = User::factory()->create();
    Enrollment::create([
        'student_id' => $student2->id,
        'class_id' => $this->class->id,
        'enrolled_at' => now(),
        'status' => 'active',
    ]);

    AssessmentMark::create([
        'assessment_id' => $assessment->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 20,
    ]);

    $response = $this->actingAs($this->teacher)
        ->getJson("/api/v1/teacher/assessments/{$assessment->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.assessment.id', $assessment->id);

    // Both students should appear (left-join pattern)
    expect($response->json('data.students'))->toHaveCount(2);

    // Student with marks should have grade
    $markedStudent = collect($response->json('data.students'))
        ->firstWhere('student_id', $this->student->id);
    expect((float) $markedStudent['marks_obtained'])->toBe(20.0);
    expect($markedStudent['grade'])->toBe('A');

    // Student without marks should have null grade
    $unmarkedStudent = collect($response->json('data.students'))
        ->firstWhere('student_id', $student2->id);
    expect($unmarkedStudent['marks_obtained'])->toBeNull();
    expect($unmarkedStudent['grade'])->toBeNull();
});

// ── Teacher Student Summary ──

it('teacher can view student grade summary', function () {
    $assessment = Assessment::factory()->create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'total_marks' => 50,
    ]);

    AssessmentMark::create([
        'assessment_id' => $assessment->id,
        'student_id' => $this->student->id,
        'marks_obtained' => 35,
    ]);

    $response = $this->actingAs($this->teacher)
        ->getJson("/api/v1/teacher/classes/{$this->class->id}/students/{$this->student->id}/grades");

    $response->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('data.subjects'))->toHaveCount(1);
    expect((float) $response->json('data.overall.percentage'))->toBe(70.0);
    expect($response->json('data.overall.grade'))->toBe('B');
});
