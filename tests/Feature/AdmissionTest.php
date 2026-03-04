<?php

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Branch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->program = Program::factory()->create();
    $this->branch = Branch::factory()->create();
});

// ── Step 1: Create Application ──

it('creates a draft application', function () {
    $response = $this->actingAs($this->user)->postJson('/api/v1/applications', [
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'study_mode' => 'at_home',
        'city' => 'Lahore',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.application.status', 'draft')
        ->assertJsonPath('data.application.study_mode', 'at_home');

    expect($response->json('data.application.application_number'))->toStartWith('BCI-');
});

it('rejects application creation without auth', function () {
    $response = $this->postJson('/api/v1/applications', [
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'study_mode' => 'at_home',
    ]);

    $response->assertUnauthorized();
});

it('validates required fields on application creation', function () {
    $response = $this->actingAs($this->user)->postJson('/api/v1/applications', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['program_id', 'branch_id', 'study_mode']);
});

it('rejects invalid program_id', function () {
    $response = $this->actingAs($this->user)->postJson('/api/v1/applications', [
        'program_id' => 999,
        'branch_id' => $this->branch->id,
        'study_mode' => 'at_home',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['program_id']);
});

it('rejects invalid study_mode', function () {
    $response = $this->actingAs($this->user)->postJson('/api/v1/applications', [
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'study_mode' => 'invalid',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['study_mode']);
});

// ── Step 2: Personal Details ──

it('saves personal details and education', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->putJson("/api/v1/applications/{$application->id}/personal-details", [
        'father_name' => 'Muhammad Ahmad',
        'father_cnic' => '35202-1234567-1',
        'father_phone' => '03001234567',
        'gender' => 'male',
        'date_of_birth' => '2005-06-15',
        'nationality' => 'Pakistani',
        'religion' => 'Islam',
        'mother_tongue' => 'Urdu',
        'postal_address' => '123 Main Street, Lahore',
        'permanent_address' => '123 Main Street, Lahore',
        'same_address' => true,
        'education' => [
            [
                'qualification' => 'Matric',
                'board_university' => 'BISE Lahore',
                'roll_no' => '123456',
                'exam_type' => 'Annual',
                'exam_year' => 2024,
                'total_marks' => 1100,
                'obtained_marks' => 900,
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.application.personal_detail.father_name', 'Muhammad Ahmad');

    expect($application->refresh()->education)->toHaveCount(1);
});

it('prevents IDOR on personal details update', function () {
    $otherUser = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $otherUser->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->putJson("/api/v1/applications/{$application->id}/personal-details", [
        'father_name' => 'Hacker',
        'father_cnic' => '35202-1234567-1',
        'father_phone' => '03001234567',
        'gender' => 'male',
        'date_of_birth' => '2005-06-15',
        'nationality' => 'Pakistani',
        'religion' => 'Islam',
        'postal_address' => 'Hack Street',
        'permanent_address' => 'Hack Street',
        'same_address' => true,
        'education' => [
            [
                'qualification' => 'Matric',
                'board_university' => 'BISE Lahore',
                'exam_year' => 2024,
                'total_marks' => 1100,
                'obtained_marks' => 900,
            ],
        ],
    ]);

    $response->assertNotFound();
});

it('prevents update on submitted application', function () {
    $application = Application::factory()->submitted()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->putJson("/api/v1/applications/{$application->id}/personal-details", [
        'father_name' => 'Too Late',
        'father_cnic' => '35202-1234567-1',
        'father_phone' => '03001234567',
        'gender' => 'male',
        'date_of_birth' => '2005-06-15',
        'nationality' => 'Pakistani',
        'religion' => 'Islam',
        'postal_address' => 'Street',
        'permanent_address' => 'Street',
        'same_address' => true,
        'education' => [
            [
                'qualification' => 'Matric',
                'board_university' => 'BISE Lahore',
                'exam_year' => 2024,
                'total_marks' => 1100,
                'obtained_marks' => 900,
            ],
        ],
    ]);

    $response->assertForbidden();
});

it('validates personal details fields', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->putJson("/api/v1/applications/{$application->id}/personal-details", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'father_name', 'father_cnic', 'father_phone', 'gender',
            'date_of_birth', 'nationality', 'religion',
            'postal_address', 'permanent_address', 'same_address', 'education',
        ]);
});

it('validates CNIC format on personal details', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->putJson("/api/v1/applications/{$application->id}/personal-details", [
        'father_name' => 'Test',
        'father_cnic' => 'invalid-cnic',
        'father_phone' => '03001234567',
        'gender' => 'male',
        'date_of_birth' => '2005-06-15',
        'nationality' => 'Pakistani',
        'religion' => 'Islam',
        'postal_address' => 'Street',
        'permanent_address' => 'Street',
        'same_address' => true,
        'education' => [
            ['qualification' => 'Matric', 'board_university' => 'BISE', 'exam_year' => 2024, 'total_marks' => 1100, 'obtained_marks' => 900],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['father_cnic']);
});

// ── Step 3: Extras (Scholarship) ──

it('saves extras/scholarship information', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->putJson("/api/v1/applications/{$application->id}/extras", [
        'study_from' => 'within_pakistan',
        'prior_computer_knowledge' => true,
        'has_computer' => true,
        'internet_type' => 'fiber',
        'heard_about_us' => 'Facebook',
        'scholarship_interest' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.application.extras.study_from', 'within_pakistan')
        ->assertJsonPath('data.application.extras.scholarship_interest', false);
});

it('prevents IDOR on extras update', function () {
    $otherUser = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $otherUser->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->putJson("/api/v1/applications/{$application->id}/extras", [
        'prior_computer_knowledge' => true,
        'has_computer' => true,
        'scholarship_interest' => false,
    ]);

    $response->assertNotFound();
});

// ── Step 4: Document Upload ──

it('uploads a document successfully', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $file = UploadedFile::fake()->image('photo.jpg', 200, 200)->size(500);

    $response = $this->actingAs($this->user)->postJson("/api/v1/applications/{$application->id}/documents", [
        'document_type' => 'photo',
        'file' => $file,
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.document.document_type', 'photo')
        ->assertJsonPath('data.document.original_name', 'photo.jpg');
});

it('replaces existing document of same type', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $file1 = UploadedFile::fake()->image('old.jpg')->size(100);
    $this->actingAs($this->user)->postJson("/api/v1/applications/{$application->id}/documents", [
        'document_type' => 'cnic_front',
        'file' => $file1,
    ]);

    $file2 = UploadedFile::fake()->image('new.jpg')->size(100);
    $this->actingAs($this->user)->postJson("/api/v1/applications/{$application->id}/documents", [
        'document_type' => 'cnic_front',
        'file' => $file2,
    ]);

    expect($application->documents()->where('document_type', 'cnic_front')->count())->toBe(1);
});

it('deletes a document', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $file = UploadedFile::fake()->image('photo.jpg')->size(100);
    $uploadResponse = $this->actingAs($this->user)->postJson("/api/v1/applications/{$application->id}/documents", [
        'document_type' => 'photo',
        'file' => $file,
    ]);

    $documentId = $uploadResponse->json('data.document.id');

    $response = $this->actingAs($this->user)->deleteJson("/api/v1/applications/{$application->id}/documents/{$documentId}");

    $response->assertOk()
        ->assertJsonPath('success', true);

    expect($application->documents()->count())->toBe(0);
});

it('prevents IDOR on document upload', function () {
    $otherUser = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $otherUser->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $file = UploadedFile::fake()->image('photo.jpg')->size(100);

    $response = $this->actingAs($this->user)->postJson("/api/v1/applications/{$application->id}/documents", [
        'document_type' => 'photo',
        'file' => $file,
    ]);

    $response->assertNotFound();
});

it('validates document type', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $file = UploadedFile::fake()->image('photo.jpg')->size(100);

    $response = $this->actingAs($this->user)->postJson("/api/v1/applications/{$application->id}/documents", [
        'document_type' => 'invalid_type',
        'file' => $file,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['document_type']);
});

it('rejects file exceeding size limit', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $file = UploadedFile::fake()->image('huge.jpg')->size(6000); // 6MB, exceeds 5MB limit

    $response = $this->actingAs($this->user)->postJson("/api/v1/applications/{$application->id}/documents", [
        'document_type' => 'cnic_front',
        'file' => $file,
    ]);

    $response->assertUnprocessable();
});

// ── Step 5: Review & Submit ──

it('returns full application review data', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->getJson("/api/v1/applications/{$application->id}/review");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'application' => [
                    'id', 'application_number', 'status',
                    'program', 'branch',
                ],
            ],
        ]);
});

it('submits application successfully', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->postJson("/api/v1/applications/{$application->id}/submit");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.application.status', 'submitted');

    expect($application->refresh()->submitted_at)->not->toBeNull();
});

it('prevents double submission', function () {
    $application = Application::factory()->submitted()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->postJson("/api/v1/applications/{$application->id}/submit");

    $response->assertForbidden();
});

it('prevents IDOR on submit', function () {
    $otherUser = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $otherUser->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->postJson("/api/v1/applications/{$application->id}/submit");

    $response->assertNotFound();
});

it('prevents IDOR on review', function () {
    $otherUser = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $otherUser->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->getJson("/api/v1/applications/{$application->id}/review");

    $response->assertNotFound();
});

it('shows application details', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->getJson("/api/v1/applications/{$application->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.application.id', $application->id);
});
