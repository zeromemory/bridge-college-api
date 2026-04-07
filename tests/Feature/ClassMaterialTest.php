<?php

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassMaterial;
use App\Models\ClassRoom;
use App\Models\ClassSubjectTeacher;
use App\Models\Program;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('filesystems.default'));

    $this->teacher = User::factory()->teacher()->create();
    $this->otherTeacher = User::factory()->teacher()->create();
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

// ── Upload (file type) ──

it('teacher can upload a file material to a subject they teach', function () {
    $file = UploadedFile::fake()->create('lecture.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/materials", [
            'subject_id' => $this->subject->id,
            'title' => 'Week 1 Lecture',
            'description' => 'Intro chapter',
            'type' => 'file',
            'file' => $file,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Week 1 Lecture')
        ->assertJsonPath('data.type', 'file');

    $this->assertDatabaseHas('class_materials', [
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'uploaded_by' => $this->teacher->id,
        'title' => 'Week 1 Lecture',
        'type' => 'file',
    ]);
});

// ── Upload (link type) ──

it('teacher can upload a link material', function () {
    $response = $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/materials", [
            'subject_id' => $this->subject->id,
            'title' => 'Khan Academy Video',
            'type' => 'link',
            'external_url' => 'https://www.khanacademy.org/some-video',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'link')
        ->assertJsonPath('data.external_url', 'https://www.khanacademy.org/some-video');
});

it('rejects invalid URL for link material', function () {
    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/materials", [
            'subject_id' => $this->subject->id,
            'title' => 'Bad Link',
            'type' => 'link',
            'external_url' => 'not-a-url',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['external_url']);
});

it('rejects file material missing the file', function () {
    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/materials", [
            'subject_id' => $this->subject->id,
            'title' => 'No file attached',
            'type' => 'file',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('rejects file with disallowed extension even if MIME is faked', function () {
    $bad = UploadedFile::fake()->create('evil.php', 10, 'application/pdf');

    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/materials", [
            'subject_id' => $this->subject->id,
            'title' => 'Suspicious',
            'type' => 'file',
            'file' => $bad,
        ])
        ->assertUnprocessable();
});

it('rejects oversized files', function () {
    // 11 MB file (cap is 10 MB)
    $big = UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf');

    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/materials", [
            'subject_id' => $this->subject->id,
            'title' => 'Too big',
            'type' => 'file',
            'file' => $big,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

// ── Authorization ──

it('teacher cannot upload to a subject they do not teach in this class', function () {
    $otherSubject = Subject::factory()->create();

    $this->actingAs($this->teacher)
        ->postJson("/api/v1/teacher/classes/{$this->class->id}/materials", [
            'subject_id' => $otherSubject->id,
            'title' => 'Sneaky',
            'type' => 'link',
            'external_url' => 'https://example.com',
        ])
        ->assertForbidden();
});

it('teacher cannot delete material uploaded by another teacher', function () {
    // Each subject in a class has exactly one teacher (unique constraint),
    // so the other teacher must be assigned to a DIFFERENT subject in the
    // same class. Both teachers can then access the class, but each only
    // owns their own materials.
    $otherSubject = Subject::factory()->create();
    ClassSubjectTeacher::create([
        'class_id' => $this->class->id,
        'subject_id' => $otherSubject->id,
        'teacher_id' => $this->otherTeacher->id,
    ]);

    $material = ClassMaterial::create([
        'class_id' => $this->class->id,
        'subject_id' => $otherSubject->id,
        'uploaded_by' => $this->otherTeacher->id,
        'title' => 'Other teacher file',
        'type' => 'link',
        'external_url' => 'https://example.com',
    ]);

    $this->actingAs($this->teacher)
        ->deleteJson("/api/v1/teacher/classes/{$this->class->id}/materials/{$material->id}")
        ->assertForbidden();
});

it('teacher can delete their own material', function () {
    $material = ClassMaterial::create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'uploaded_by' => $this->teacher->id,
        'title' => 'Mine',
        'type' => 'link',
        'external_url' => 'https://example.com',
    ]);

    $this->actingAs($this->teacher)
        ->deleteJson("/api/v1/teacher/classes/{$this->class->id}/materials/{$material->id}")
        ->assertOk();

    $this->assertSoftDeleted('class_materials', ['id' => $material->id]);
});

// ── List ──

it('lists materials for a class the teacher teaches', function () {
    ClassMaterial::create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'uploaded_by' => $this->teacher->id,
        'title' => 'M1',
        'type' => 'link',
        'external_url' => 'https://a.example',
    ]);
    ClassMaterial::create([
        'class_id' => $this->class->id,
        'subject_id' => $this->subject->id,
        'uploaded_by' => $this->teacher->id,
        'title' => 'M2',
        'type' => 'link',
        'external_url' => 'https://b.example',
    ]);

    $response = $this->actingAs($this->teacher)
        ->getJson("/api/v1/teacher/classes/{$this->class->id}/materials");

    $response->assertOk()
        ->assertJsonPath('success', true);
});
