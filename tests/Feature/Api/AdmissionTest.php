<?php

use App\Models\Application;
use App\Models\Branch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// === Step 1: Create Application ===

it('creates a draft application', function () {
    $user = User::factory()->create();
    $program = Program::factory()->create();
    $branch = Branch::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/applications', [
            'program_id' => $program->id,
            'branch_id' => $branch->id,
            'study_mode' => 'at_home',
            'city' => 'Lahore',
        ])
        ->assertStatus(201)
        ->assertJson(['success' => true])
        ->assertJsonPath('data.application.status', 'draft')
        ->assertJsonPath('data.application.program.id', $program->id)
        ->assertJsonPath('data.application.branch.id', $branch->id);

    expect(Application::where('user_id', $user->id)->count())->toBe(1);
});

it('generates unique application number on create', function () {
    $user = User::factory()->create();
    $program = Program::factory()->create();
    $branch = Branch::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/v1/applications', [
            'program_id' => $program->id,
            'branch_id' => $branch->id,
            'study_mode' => 'virtual_campus',
        ]);

    $appNumber = $response->json('data.application.application_number');
    expect($appNumber)->toStartWith('BCI-' . date('Y') . '-');
});

it('rejects application with invalid program_id', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/applications', [
            'program_id' => 999,
            'branch_id' => $branch->id,
            'study_mode' => 'at_home',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['program_id']);
});

it('rejects application with invalid study_mode', function () {
    $user = User::factory()->create();
    $program = Program::factory()->create();
    $branch = Branch::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/applications', [
            'program_id' => $program->id,
            'branch_id' => $branch->id,
            'study_mode' => 'invalid',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['study_mode']);
});

it('requires authentication to create application', function () {
    $this->postJson('/api/v1/applications', [])
        ->assertStatus(401);
});

// === Step 2: Update Personal Details ===

it('updates personal details for draft application', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->putJson("/api/v1/applications/{$application->id}/personal-details", validPersonalDetails())
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($application->fresh()->personalDetail)->not->toBeNull();
    expect($application->fresh()->education)->toHaveCount(1);
});

it('rejects personal details for submitted application', function () {
    $user = User::factory()->create();
    $application = Application::factory()->submitted()->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->putJson("/api/v1/applications/{$application->id}/personal-details", validPersonalDetails())
        ->assertStatus(403);
});

it('rejects personal details from another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $other->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->putJson("/api/v1/applications/{$application->id}/personal-details", validPersonalDetails())
        ->assertStatus(404); // findOrFail scoped to user
});

it('validates required personal detail fields', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->putJson("/api/v1/applications/{$application->id}/personal-details", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'father_name', 'father_cnic', 'father_phone', 'gender',
            'date_of_birth', 'nationality', 'religion', 'postal_address',
            'permanent_address', 'same_address', 'education',
        ]);
});

// === Step 3: Update Extras ===

it('updates extras for draft application', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->putJson("/api/v1/applications/{$application->id}/extras", [
            'study_from' => 'within_pakistan',
            'prior_computer_knowledge' => true,
            'has_computer' => true,
            'internet_type' => 'fiber',
            'heard_about_us' => 'Facebook',
            'scholarship_interest' => false,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($application->fresh()->extras)->not->toBeNull();
});

it('rejects invalid internet_type', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->putJson("/api/v1/applications/{$application->id}/extras", [
            'prior_computer_knowledge' => true,
            'has_computer' => true,
            'internet_type' => 'satellite',
            'scholarship_interest' => false,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['internet_type']);
});

// === Step 4: Upload Documents ===

it('uploads a document for draft application', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    $file = UploadedFile::fake()->image('photo.jpg', 200, 200)->size(500);

    $this->actingAs($user)
        ->postJson("/api/v1/applications/{$application->id}/documents", [
            'document_type' => 'photo',
            'file' => $file,
        ])
        ->assertStatus(201)
        ->assertJson(['success' => true])
        ->assertJsonPath('data.document.document_type', 'photo');

    expect($application->fresh()->documents)->toHaveCount(1);
});

it('replaces existing document of same type', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    // Upload first photo
    $this->actingAs($user)
        ->postJson("/api/v1/applications/{$application->id}/documents", [
            'document_type' => 'cnic_front',
            'file' => UploadedFile::fake()->image('front1.jpg')->size(500),
        ])
        ->assertStatus(201);

    // Upload second photo with same type — should replace
    $this->actingAs($user)
        ->postJson("/api/v1/applications/{$application->id}/documents", [
            'document_type' => 'cnic_front',
            'file' => UploadedFile::fake()->image('front2.jpg')->size(500),
        ])
        ->assertStatus(201);

    expect($application->fresh()->documents)->toHaveCount(1);
    expect($application->fresh()->documents->first()->original_name)->toBe('front2.jpg');
});

it('allows multiple additional documents', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/applications/{$application->id}/documents", [
            'document_type' => 'additional',
            'file' => UploadedFile::fake()->create('doc1.pdf', 500, 'application/pdf'),
        ])
        ->assertStatus(201);

    $this->actingAs($user)
        ->postJson("/api/v1/applications/{$application->id}/documents", [
            'document_type' => 'additional',
            'file' => UploadedFile::fake()->create('doc2.pdf', 500, 'application/pdf'),
        ])
        ->assertStatus(201);

    expect($application->fresh()->documents)->toHaveCount(2);
});

it('rejects photo over 1MB', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    $file = UploadedFile::fake()->image('big-photo.jpg')->size(1500);

    $this->actingAs($user)
        ->postJson("/api/v1/applications/{$application->id}/documents", [
            'document_type' => 'photo',
            'file' => $file,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'UPLOAD_ERROR');
});

it('rejects document upload for submitted application', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $application = Application::factory()->submitted()->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/applications/{$application->id}/documents", [
            'document_type' => 'photo',
            'file' => UploadedFile::fake()->image('photo.jpg')->size(500),
        ])
        ->assertStatus(403);
});

it('deletes a document from draft application', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    $uploadResponse = $this->actingAs($user)
        ->postJson("/api/v1/applications/{$application->id}/documents", [
            'document_type' => 'cnic_front',
            'file' => UploadedFile::fake()->image('front.jpg')->size(500),
        ]);

    $documentId = $uploadResponse->json('data.document.id');

    $this->actingAs($user)
        ->deleteJson("/api/v1/applications/{$application->id}/documents/{$documentId}")
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($application->fresh()->documents)->toHaveCount(0);
});

it('rejects invalid document type', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/applications/{$application->id}/documents", [
            'document_type' => 'passport',
            'file' => UploadedFile::fake()->image('photo.jpg')->size(500),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['document_type']);
});

// === Step 5: Review & Submit ===

it('retrieves application review with all relations', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->getJson("/api/v1/applications/{$application->id}/review")
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'data' => [
                'application' => [
                    'id', 'application_number', 'status',
                    'program', 'branch',
                ],
            ],
        ]);
});

it('submits a draft application', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/applications/{$application->id}/submit")
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonPath('data.application.status', 'submitted');

    expect($application->fresh()->status)->toBe('submitted');
    expect($application->fresh()->submitted_at)->not->toBeNull();
});

it('rejects submitting already submitted application', function () {
    $user = User::factory()->create();
    $application = Application::factory()->submitted()->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->postJson("/api/v1/applications/{$application->id}/submit")
        ->assertStatus(403);
});

it('shows application details to owner', function () {
    $user = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->getJson("/api/v1/applications/{$application->id}")
        ->assertOk()
        ->assertJson(['success' => true]);
});

it('hides application from non-owner', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $application = Application::factory()->create([
        'user_id' => $other->id,
    ]);

    $this->actingAs($user)
        ->getJson("/api/v1/applications/{$application->id}")
        ->assertStatus(404);
});

// === Helper ===

function validPersonalDetails(): array
{
    return [
        'father_name' => 'Muhammad Ahmed',
        'father_cnic' => '35202-1234567-1',
        'father_phone' => '03001234567',
        'guardian_name' => null,
        'guardian_relationship' => null,
        'guardian_income' => null,
        'gender' => 'male',
        'date_of_birth' => '2005-06-15',
        'nationality' => 'Pakistani',
        'religion' => 'Islam',
        'mother_tongue' => 'Urdu',
        'postal_address' => '123 Main Street, Lahore',
        'permanent_address' => '123 Main Street, Lahore',
        'same_address' => true,
        'cnic_issuance_date' => '2023-01-10',
        'phone_landline' => '042-36831098',
        'education' => [
            [
                'qualification' => 'Matriculation',
                'board_university' => 'BISE Lahore',
                'roll_no' => '123456',
                'registration_no' => 'REG-2023-001',
                'exam_type' => 'Annual',
                'exam_year' => 2023,
                'total_marks' => 550,
                'obtained_marks' => 480,
            ],
        ],
    ];
}
