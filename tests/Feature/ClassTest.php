<?php

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassRoom;
use App\Models\Program;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->student = User::factory()->create();
    $this->program = Program::factory()->create();
    $this->branch = Branch::factory()->create();
    $this->session = AcademicSession::factory()->active()->create();
});

// ── Auth ──

it('denies student access to classes', function () {
    $this->actingAs($this->student)->getJson('/api/v1/admin/classes')
        ->assertForbidden();
});

// ── List ──

it('lists all classes', function () {
    ClassRoom::factory()->count(3)->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/classes');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

it('filters classes by academic session', function () {
    $otherSession = AcademicSession::factory()->create();

    ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);
    ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $otherSession->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/admin/classes?academic_session_id={$this->session->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Create ──

it('creates a class', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/classes', [
        'name' => 'Section A',
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
        'capacity' => 40,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Section A');

    $this->assertDatabaseHas('classes', ['name' => 'Section A']);
});

it('validates required fields when creating class', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/classes', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'program_id', 'branch_id', 'academic_session_id']);
});

// ── Show ──

it('shows a class with relationships', function () {
    $class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);

    $response = $this->actingAs($this->admin)->getJson("/api/v1/admin/classes/{$class->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $class->id)
        ->assertJsonStructure(['data' => ['program', 'branch', 'academic_session']]);
});

// ── Update ──

it('updates a class', function () {
    $class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);

    $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/classes/{$class->id}", [
        'name' => 'Section B',
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Section B');
});

// ── Delete ──

it('soft deletes a class', function () {
    $class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);

    $response = $this->actingAs($this->admin)->deleteJson("/api/v1/admin/classes/{$class->id}");

    $response->assertOk();
    $this->assertSoftDeleted('classes', ['id' => $class->id]);
});

// ── Assign/Unassign Teacher ──

it('assigns a teacher to a subject in a class', function () {
    $class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->create();

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/classes/{$class->id}/assign-teacher", [
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.teacher_id', $teacher->id)
        ->assertJsonPath('data.subject_id', $subject->id);

    $this->assertDatabaseHas('class_subject_teacher', [
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
    ]);
});

it('unassigns a teacher from a subject in a class', function () {
    $class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);
    $teacher = User::factory()->teacher()->create();
    $subject = Subject::factory()->create();

    // First assign
    $this->actingAs($this->admin)->postJson("/api/v1/admin/classes/{$class->id}/assign-teacher", [
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
    ]);

    // Then unassign
    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/classes/{$class->id}/unassign-teacher", [
        'subject_id' => $subject->id,
    ]);

    $response->assertOk();
    $this->assertDatabaseMissing('class_subject_teacher', [
        'class_id' => $class->id,
        'subject_id' => $subject->id,
    ]);
});

// ── Enrollment ──

it('enrolls a student in a class', function () {
    $class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/classes/{$class->id}/enroll", [
        'student_id' => $this->student->id,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('enrollments', [
        'class_id' => $class->id,
        'student_id' => $this->student->id,
        'status' => 'active',
    ]);
});

it('unenrolls a student from a class', function () {
    $class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);

    // Enroll first
    $this->actingAs($this->admin)->postJson("/api/v1/admin/classes/{$class->id}/enroll", [
        'student_id' => $this->student->id,
    ]);

    // Unenroll
    $response = $this->actingAs($this->admin)->deleteJson("/api/v1/admin/classes/{$class->id}/unenroll/{$this->student->id}");

    $response->assertOk();

    $this->assertDatabaseHas('enrollments', [
        'class_id' => $class->id,
        'student_id' => $this->student->id,
        'status' => 'withdrawn',
    ]);
});

it('lists students in a class', function () {
    $class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);

    // Enroll 2 students
    $student2 = User::factory()->create();
    $this->actingAs($this->admin)->postJson("/api/v1/admin/classes/{$class->id}/enroll", [
        'student_id' => $this->student->id,
    ]);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/classes/{$class->id}/enroll", [
        'student_id' => $student2->id,
    ]);

    $response = $this->actingAs($this->admin)->getJson("/api/v1/admin/classes/{$class->id}/students");

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});
