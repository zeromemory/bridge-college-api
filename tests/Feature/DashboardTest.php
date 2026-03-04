<?php

use App\Models\Application;
use App\Models\Branch;
use App\Models\FeeChallan;
use App\Models\Program;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->program = Program::factory()->create();
    $this->branch = Branch::factory()->create();
});

it('returns student applications on dashboard', function () {
    Application::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data.applications');
});

it('returns empty array for new student', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonCount(0, 'data.applications');
});

it('returns only own applications', function () {
    $otherUser = User::factory()->create();

    Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);
    Application::factory()->create([
        'user_id' => $otherUser->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonCount(1, 'data.applications');
});

it('includes program and branch in dashboard response', function () {
    Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'applications' => [
                    ['id', 'application_number', 'program', 'branch', 'status', 'study_mode', 'fee_status'],
                ],
            ],
        ]);
});

it('shows fee_status as paid when challan is paid', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    FeeChallan::factory()->paid()->create(['application_id' => $application->id]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('data.applications.0.fee_status', 'paid');
});

it('shows fee_status as pending when challan is not paid', function () {
    $application = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    FeeChallan::factory()->create(['application_id' => $application->id]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('data.applications.0.fee_status', 'pending');
});

it('shows fee_status as null when no challan exists', function () {
    Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard');

    $response->assertOk()
        ->assertJsonPath('data.applications.0.fee_status', null);
});

it('rejects unauthenticated dashboard access', function () {
    $response = $this->getJson('/api/v1/dashboard');

    $response->assertUnauthorized();
});

it('orders applications by newest first', function () {
    $old = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'created_at' => now()->subDays(5),
    ]);
    $new = Application::factory()->create([
        'user_id' => $this->user->id,
        'program_id' => $this->program->id,
        'branch_id' => $this->branch->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard');

    $response->assertOk();
    $ids = collect($response->json('data.applications'))->pluck('id')->toArray();
    expect($ids[0])->toBe($new->id);
});
