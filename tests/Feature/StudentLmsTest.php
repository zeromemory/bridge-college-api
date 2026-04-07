<?php

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassMaterial;
use App\Models\ClassRoom;
use App\Models\ClassSubjectTeacher;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('filesystems.default'));

    $this->student = User::factory()->create();
    $this->otherStudent = User::factory()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->program = Program::factory()->create();
    $this->branch = Branch::factory()->create();
    $this->session = AcademicSession::factory()->active()->create();
    $this->subject = Subject::factory()->create();

    $this->class = ClassRoom::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'academic_session_id' => $this->session->id,
    ]);

    ClassSubjectTeacher::create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
    ]);
});

// ── Role guards ──

it('denies teacher access to student LMS routes', function () {
    $this->actingAs($this->teacher)
        ->getJson('/api/v1/student/lms/my-class')
        ->assertForbidden();
});

it('denies unauthenticated access to student LMS routes', function () {
    $this->getJson('/api/v1/student/lms/my-class')->assertUnauthorized();
});

// ── My class ──

it('returns null class when student has no active enrollment', function () {
    $response = $this->actingAs($this->student)->getJson('/api/v1/student/lms/my-class');

    $response->assertOk()
        ->assertJsonPath('data.class', null)
        ->assertJsonPath('data.subjects', []);
});

it('returns the enrolled class with subjects, teachers, and materials', function () {
    Enrollment::create([
        'student_id' => $this->student->id,
        'class_id' => $this->class->id,
        'enrolled_at' => now(),
        'status' => 'active',
    ]);

    ClassMaterial::create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'uploaded_by' => $this->teacher->id,
        'title' => 'Hello',
        'type' => 'link',
        'external_url' => 'https://example.com',
    ]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/v1/student/lms/my-class');

    $response->assertOk()
        ->assertJsonPath('data.class.id', $this->class->id)
        ->assertJsonCount(1, 'data.subjects');
});

// ── Download ──

it('enrolled student can download a file material', function () {
    $disk = config('filesystems.default');
    $path = "class-materials/{$this->class->id}/example.pdf";
    Storage::disk($disk)->put($path, 'PDF body');

    Enrollment::create([
        'student_id' => $this->student->id,
        'class_id' => $this->class->id,
        'enrolled_at' => now(),
        'status' => 'active',
    ]);

    $material = ClassMaterial::create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'uploaded_by' => $this->teacher->id,
        'title' => 'Lecture',
        'type' => 'file',
        'file_path' => $path,
        'file_name' => 'lecture.pdf',
        'file_size' => 8,
        'mime_type' => 'application/pdf',
    ]);

    $response = $this->actingAs($this->student)
        ->get("/api/v1/student/lms/materials/{$material->id}/download");

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('lecture.pdf');
});

it('non-enrolled student cannot download material', function () {
    $material = ClassMaterial::create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'uploaded_by' => $this->teacher->id,
        'title' => 'Lecture',
        'type' => 'file',
        'file_path' => 'class-materials/x.pdf',
        'file_name' => 'x.pdf',
        'file_size' => 1,
        'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($this->otherStudent)
        ->getJson("/api/v1/student/lms/materials/{$material->id}/download")
        ->assertForbidden();
});

it('withdrawn student loses access to materials', function () {
    Enrollment::create([
        'student_id' => $this->student->id,
        'class_id' => $this->class->id,
        'enrolled_at' => now()->subWeek(),
        'status' => 'withdrawn',
    ]);

    $material = ClassMaterial::create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'uploaded_by' => $this->teacher->id,
        'title' => 'Lecture',
        'type' => 'file',
        'file_path' => 'class-materials/x.pdf',
        'file_name' => 'x.pdf',
        'file_size' => 1,
        'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($this->student)
        ->getJson("/api/v1/student/lms/materials/{$material->id}/download")
        ->assertForbidden();
});
