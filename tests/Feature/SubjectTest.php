<?php

use App\Models\Program;
use App\Models\Subject;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->student = User::factory()->create();
});

// ── Auth ──

it('denies student access to subjects', function () {
    $this->actingAs($this->student)->getJson('/api/v1/admin/subjects')
        ->assertForbidden();
});

// ── List ──

it('lists all subjects', function () {
    Subject::factory()->count(5)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/subjects');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(5, 'data');
});

// ── Create ──

it('creates a subject', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/subjects', [
        'name' => 'Mathematics',
        'code' => 'MATH',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Mathematics')
        ->assertJsonPath('data.code', 'MATH');

    $this->assertDatabaseHas('subjects', ['code' => 'MATH']);
});

it('validates required fields when creating subject', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/subjects', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'code']);
});

it('validates unique subject code', function () {
    Subject::factory()->create(['code' => 'MATH']);

    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/subjects', [
        'name' => 'Another Math',
        'code' => 'MATH',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

// ── Update ──

it('updates a subject', function () {
    $subject = Subject::factory()->create();

    $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/subjects/{$subject->id}", [
        'name' => 'Updated Subject',
        'code' => $subject->code,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Subject');
});

// ── Delete ──

it('soft deletes a subject', function () {
    $subject = Subject::factory()->create();

    $response = $this->actingAs($this->admin)->deleteJson("/api/v1/admin/subjects/{$subject->id}");

    $response->assertOk();
    $this->assertSoftDeleted('subjects', ['id' => $subject->id]);
});

// ── Program Subjects ──

it('lists subjects for a program', function () {
    $program = Program::factory()->create();
    $subjects = Subject::factory()->count(3)->create();

    $program->subjects()->attach($subjects->pluck('id')->mapWithKeys(fn ($id) => [$id => ['is_elective' => false]]));

    $response = $this->actingAs($this->admin)->getJson("/api/v1/admin/programs/{$program->id}/subjects");

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('syncs program subjects', function () {
    $program = Program::factory()->create();
    $subjects = Subject::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/programs/{$program->id}/subjects", [
        'subjects' => [
            ['subject_id' => $subjects[0]->id, 'is_elective' => false],
            ['subject_id' => $subjects[1]->id, 'is_elective' => true],
        ],
    ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    $this->assertDatabaseHas('program_subject', [
        'program_id' => $program->id,
        'subject_id' => $subjects[1]->id,
        'is_elective' => true,
    ]);
});
