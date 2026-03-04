<?php

use App\Models\Application;
use App\Models\Branch;
use App\Models\FeeChallan;
use App\Models\Program;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->student = User::factory()->create();
    $this->program = Program::factory()->create();
    $this->branch = Branch::factory()->create();
});

// ── Role Checks ──

it('denies student access to admin routes', function () {
    $this->actingAs($this->student)->getJson('/api/v1/admin/stats')
        ->assertForbidden();
});

it('denies unauthenticated access to admin routes', function () {
    $this->getJson('/api/v1/admin/stats')
        ->assertUnauthorized();
});

// ── Stats ──

it('returns admin stats', function () {
    Application::factory()->count(3)->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/stats');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', 3)
        ->assertJsonStructure([
            'data' => ['total', 'by_status', 'by_program', 'by_branch', 'today'],
        ]);
});

// ── Application Management ──

it('lists all applications with pagination', function () {
    Application::factory()->count(3)->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/applications');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'applications' => [
                    'data', 'current_page', 'total',
                ],
            ],
        ]);
});

it('filters applications by status', function () {
    Application::factory()->submitted()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);
    Application::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/applications?status=submitted');

    $response->assertOk();
    expect($response->json('data.applications.total'))->toBe(1);
});

it('searches applications by application number', function () {
    $app = Application::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/applications?search=' . $app->application_number);

    $response->assertOk();
    expect($response->json('data.applications.total'))->toBe(1);
});

it('shows full application detail for admin', function () {
    $application = Application::factory()->create([
        'user_id' => $this->student->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->admin)->getJson("/api/v1/admin/applications/{$application->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.application.id', $application->id);
});

it('accepts a submitted application', function () {
    $application = Application::factory()->submitted()->create([
        'user_id' => $this->student->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/applications/{$application->id}/accept", [
        'admin_notes' => 'Welcome to BCI!',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.application.status', 'accepted');

    $application->refresh();
    expect($application->reviewed_by)->toBe($this->admin->id)
        ->and($application->reviewed_at)->not->toBeNull();
});

it('rejects a submitted application with reason', function () {
    $application = Application::factory()->submitted()->create([
        'user_id' => $this->student->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/applications/{$application->id}/reject", [
        'admin_notes' => 'Incomplete documents.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.application.status', 'rejected');
});

it('requires reason when rejecting application', function () {
    $application = Application::factory()->submitted()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/applications/{$application->id}/reject", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['admin_notes']);
});

it('cannot accept a draft application', function () {
    $application = Application::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/applications/{$application->id}/accept");

    $response->assertUnprocessable();
});

// ── Student Management ──

it('lists students with search', function () {
    User::factory()->count(3)->create(['role' => 'student']);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/students');

    $response->assertOk()
        ->assertJsonPath('success', true);
    // +1 for beforeEach student
    expect($response->json('data.students.total'))->toBe(4);
});

it('searches students by name', function () {
    User::factory()->create(['role' => 'student', 'name' => 'Unique Test Name']);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/students?search=Unique Test');

    $response->assertOk();
    expect($response->json('data.students.total'))->toBe(1);
});

it('shows student profile with applications', function () {
    Application::factory()->create([
        'user_id' => $this->student->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->admin)->getJson("/api/v1/admin/students/{$this->student->id}");

    $response->assertOk()
        ->assertJsonPath('data.student.id', $this->student->id)
        ->assertJsonCount(1, 'data.student.applications');
});

it('toggles student active status', function () {
    expect($this->student->is_active)->toBeTrue();

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/students/{$this->student->id}/toggle-status");

    $response->assertOk();
    expect($this->student->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->postJson("/api/v1/admin/students/{$this->student->id}/toggle-status");

    expect($this->student->refresh()->is_active)->toBeTrue();
});

// ── Fee Management ──

it('generates a fee challan', function () {
    $application = Application::factory()->submitted()->create([
        'user_id' => $this->student->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/applications/{$application->id}/challan", [
        'amount' => 15000,
        'due_date' => now()->addDays(15)->format('Y-m-d'),
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true);

    expect($response->json('data.challan.challan_number'))->toStartWith('BCI-FEE-');
});

it('validates challan creation fields', function () {
    $application = Application::factory()->create([
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/applications/{$application->id}/challan", []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount', 'due_date']);
});

it('marks a challan as paid', function () {
    $challan = FeeChallan::factory()->create();

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/challans/{$challan->id}/mark-paid", [
        'payment_reference' => 'TXN-12345678',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.challan.status', 'paid');

    expect($challan->refresh()->paid_at)->not->toBeNull();
});

it('rejects marking already-paid challan', function () {
    $challan = FeeChallan::factory()->paid()->create();

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/challans/{$challan->id}/mark-paid");

    $response->assertUnprocessable();
});

it('denies student access to application management', function () {
    $this->actingAs($this->student)->getJson('/api/v1/admin/applications')
        ->assertForbidden();
});

it('denies student access to student management', function () {
    $this->actingAs($this->student)->getJson('/api/v1/admin/students')
        ->assertForbidden();
});
