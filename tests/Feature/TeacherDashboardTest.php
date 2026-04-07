<?php

use App\Models\AcademicSession;
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
    $this->otherTeacher = User::factory()->teacher()->create();
    $this->student = User::factory()->create();
    $this->program = Program::factory()->create();
    $this->branch = Branch::factory()->create();
    $this->session = AcademicSession::factory()->active()->create();
});

// ── Auth / role guards ──

it('denies unauthenticated access to teacher routes', function () {
    $this->getJson('/api/v1/teacher/classes')->assertUnauthorized();
});

it('denies admin access to teacher routes', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/teacher/classes')
        ->assertForbidden();
});

it('denies student access to teacher routes', function () {
    $this->actingAs($this->student)
        ->getJson('/api/v1/teacher/classes')
        ->assertForbidden();
});

// ── My classes ──

it('lists only the classes the teacher is involved in (homeroom or subject)', function () {
    $homeroomClass = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
        'class_teacher_id' => $this->teacher->id,
    ]);

    $subjectClass = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);
    $subject = Subject::factory()->create();
    ClassSubjectTeacher::create([
        'class_id' => $subjectClass->id,
        'subject_id' => $subject->id,
        'teacher_id' => $this->teacher->id,
    ]);

    // A class the teacher has nothing to do with
    ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);

    $response = $this->actingAs($this->teacher)->getJson('/api/v1/teacher/classes');

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($homeroomClass->id, $subjectClass->id);
});

// ── Class detail ──

it('teacher can view detail of a class they teach', function () {
    $class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
        'class_teacher_id' => $this->teacher->id,
    ]);

    $this->actingAs($this->teacher)
        ->getJson("/api/v1/teacher/classes/{$class->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $class->id);
});

it('teacher gets 403 when accessing a class they do not teach', function () {
    $class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
        'class_teacher_id' => $this->otherTeacher->id,
    ]);

    $this->actingAs($this->teacher)
        ->getJson("/api/v1/teacher/classes/{$class->id}")
        ->assertForbidden();
});

// ── Class students ──

it('class students endpoint returns only active enrollments', function () {
    $class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
        'class_teacher_id' => $this->teacher->id,
    ]);

    $activeStudent = User::factory()->create();
    $withdrawnStudent = User::factory()->create();

    Enrollment::create([
        'student_id' => $activeStudent->id,
        'class_id' => $class->id,
        'enrolled_at' => now(),
        'status' => 'active',
    ]);
    Enrollment::create([
        'student_id' => $withdrawnStudent->id,
        'class_id' => $class->id,
        'enrolled_at' => now()->subWeek(),
        'status' => 'withdrawn',
    ]);

    $this->actingAs($this->teacher)
        ->getJson("/api/v1/teacher/classes/{$class->id}/students")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
