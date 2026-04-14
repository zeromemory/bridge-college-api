<?php

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\ClassRoom;
use App\Models\ClassSubjectTeacher;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Subject;
use App\Models\User;

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
});

// ── Role Guards ──

it('unauthenticated user cannot access teacher attendance', function () {
    $this->postJson("/api/v1/teacher/classes/{$this->class->id}/attendance", [
        'date' => now()->format('Y-m-d'),
        'records' => [['student_id' => $this->student->id, 'status' => 'present']],
    ])->assertUnauthorized();
});

it('student cannot access teacher attendance routes', function () {
    $this->actingAs($this->student)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/attendance", [
            'date' => now()->format('Y-m-d'),
            'records' => [['student_id' => $this->student->id, 'status' => 'present']],
        ])->assertForbidden();
});

it('teacher cannot access student attendance routes', function () {
    $this->actingAs($this->teacher)
        ->getJson('/api/v1/student/lms/attendance')
        ->assertForbidden();
});

it('unauthenticated user cannot access student attendance', function () {
    $this->getJson('/api/v1/student/lms/attendance')
        ->assertUnauthorized();
});

// ── Teacher Mark Attendance ──

it('teacher can mark attendance for enrolled students', function () {
    $date = now()->format('Y-m-d');

    $response = $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/attendance", [
            'date' => $date,
            'records' => [
                ['student_id' => $this->student->id, 'status' => 'present', 'remarks' => 'On time'],
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('attendances', [
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'marked_by' => $this->teacher->id,
        'date' => $date,
        'status' => 'present',
        'remarks' => 'On time',
    ]);
});

it('upsert updates existing attendance record', function () {
    $date = now()->format('Y-m-d');

    // Mark as present first
    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/attendance", [
            'date' => $date,
            'records' => [['student_id' => $this->student->id, 'status' => 'present']],
        ])->assertCreated();

    // Update to absent
    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/attendance", [
            'date' => $date,
            'records' => [['student_id' => $this->student->id, 'status' => 'absent', 'remarks' => 'Called in sick']],
        ])->assertCreated();

    // Should have exactly one record (upsert, not duplicate)
    expect(Attendance::where('class_id', $this->class->id)
        ->where('student_id', $this->student->id)
        ->where('date', $date)
        ->count())->toBe(1);

    $this->assertDatabaseHas('attendances', [
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'date' => $date,
        'status' => 'absent',
        'remarks' => 'Called in sick',
    ]);
});

it('rejects future date', function () {
    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/attendance", [
            'date' => now()->addDay()->format('Y-m-d'),
            'records' => [['student_id' => $this->student->id, 'status' => 'present']],
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('rejects non-enrolled student', function () {
    $otherStudent = User::factory()->create();

    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/attendance", [
            'date' => now()->format('Y-m-d'),
            'records' => [['student_id' => $otherStudent->id, 'status' => 'present']],
        ])->assertUnprocessable();
});

it('rejects invalid status', function () {
    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/attendance", [
            'date' => now()->format('Y-m-d'),
            'records' => [['student_id' => $this->student->id, 'status' => 'invalid']],
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['records.0.status']);
});

it('teacher cannot mark attendance for class they do not teach', function () {
    $otherClass = ClassRoom::factory()->create();

    Enrollment::create([
        'student_id' => $this->student->id,
        'class_id' => $otherClass->id,
        'enrolled_at' => now(),
        'status' => 'active',
    ]);

    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$otherClass->id}/attendance", [
            'date' => now()->format('Y-m-d'),
            'records' => [['student_id' => $this->student->id, 'status' => 'present']],
        ])->assertForbidden();
});

// ── Teacher View Attendance ──

it('teacher can view class attendance for a date', function () {
    $date = now()->format('Y-m-d');

    Attendance::create([
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'marked_by' => $this->teacher->id,
        'date' => $date,
        'status' => 'present',
    ]);

    $response = $this->actingAs($this->teacher)
        ->getJson("/api/v1/teacher/classes/{$this->class->id}/attendance?date={$date}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.date', $date)
        ->assertJsonPath('data.summary.present', 1);
});

it('teacher can view monthly attendance history', function () {
    $date = now()->format('Y-m-d');

    Attendance::create([
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'marked_by' => $this->teacher->id,
        'date' => $date,
        'status' => 'present',
    ]);

    $response = $this->actingAs($this->teacher)
        ->getJson("/api/v1/teacher/classes/{$this->class->id}/attendance/history?year=" . now()->year . "&month=" . now()->month);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.year', now()->year)
        ->assertJsonPath('data.month', now()->month);

    expect($response->json('data.daily_summary'))->toHaveCount(1);
});

// ── Student View Attendance ──

it('student can view own attendance', function () {
    Attendance::create([
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'marked_by' => $this->teacher->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'present',
    ]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/v1/student/lms/attendance');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.stats.total_days', 1)
        ->assertJsonPath('data.stats.present', 1)
        ->assertJsonPath('data.stats.percentage', 100);
});

it('student can view monthly attendance', function () {
    Attendance::create([
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'marked_by' => $this->teacher->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'late',
        'remarks' => '15 minutes late',
    ]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/v1/student/lms/attendance/monthly?year=' . now()->year . '&month=' . now()->month);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.stats.late', 1)
        ->assertJsonPath('data.stats.percentage', 100); // late counts as attended
});

it('student with no enrollment gets null attendance', function () {
    $newStudent = User::factory()->create();

    $response = $this->actingAs($newStudent)
        ->getJson('/api/v1/student/lms/attendance');

    $response->assertOk()
        ->assertJsonPath('data', null);
});

// ── Admin View Attendance ──

it('admin can view class attendance for a date', function () {
    $date = now()->format('Y-m-d');

    Attendance::create([
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'marked_by' => $this->teacher->id,
        'date' => $date,
        'status' => 'present',
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/admin/classes/{$this->class->id}/attendance?date={$date}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.summary.present', 1);
});

it('admin can view monthly summary', function () {
    Attendance::create([
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'marked_by' => $this->teacher->id,
        'date' => now()->format('Y-m-d'),
        'status' => 'present',
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/admin/classes/{$this->class->id}/attendance/monthly?year=" . now()->year . "&month=" . now()->month);

    $response->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('data.student_stats'))->toHaveCount(1);
    expect($response->json('data.student_stats.0.percentage'))->toBe(100);
});

it('admin can mark attendance for any class', function () {
    $date = now()->format('Y-m-d');

    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/classes/{$this->class->id}/attendance", [
            'date' => $date,
            'records' => [
                ['student_id' => $this->student->id, 'status' => 'late', 'remarks' => 'Admin correction'],
            ],
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('attendances', [
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'marked_by' => $this->admin->id,
        'status' => 'late',
        'remarks' => 'Admin correction',
    ]);
});
