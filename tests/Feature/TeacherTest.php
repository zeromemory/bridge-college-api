<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->student = User::factory()->create();
});

// ── Auth ──

it('denies student access to teachers', function () {
    $this->actingAs($this->student)->getJson('/api/v1/admin/teachers')
        ->assertForbidden();
});

// ── List ──

it('lists all teachers', function () {
    User::factory()->teacher()->count(3)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/teachers');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

// ── Create ──

it('creates a teacher', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/teachers', [
        'name' => 'Mr. Ahmad',
        'email' => 'ahmad@bci.edu.pk',
        'cnic' => '35201-1234567-1',
        'password' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Mr. Ahmad')
        ->assertJsonPath('data.role', 'teacher');

    $this->assertDatabaseHas('users', [
        'email' => 'ahmad@bci.edu.pk',
        'role' => 'teacher',
    ]);
});

it('validates required fields when creating teacher', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/teachers', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('validates unique email for teacher', function () {
    User::factory()->teacher()->create(['email' => 'taken@bci.edu.pk']);

    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/teachers', [
        'name' => 'Another Teacher',
        'email' => 'taken@bci.edu.pk',
        'password' => 'password123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

// ── Show ──

it('shows a teacher with assignments', function () {
    $teacher = User::factory()->teacher()->create();

    $response = $this->actingAs($this->admin)->getJson("/api/v1/admin/teachers/{$teacher->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $teacher->id);
});

it('returns 404 for non-teacher user', function () {
    $response = $this->actingAs($this->admin)->getJson("/api/v1/admin/teachers/{$this->student->id}");

    $response->assertNotFound();
});

// ── Update ──

it('updates a teacher', function () {
    $teacher = User::factory()->teacher()->create();

    $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/teachers/{$teacher->id}", [
        'name' => 'Updated Name',
        'email' => $teacher->email,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');
});

// ── Toggle Status ──

it('toggles teacher active status', function () {
    $teacher = User::factory()->teacher()->create(['is_active' => true]);

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/teachers/{$teacher->id}/toggle-status");

    $response->assertOk();
    expect($teacher->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->postJson("/api/v1/admin/teachers/{$teacher->id}/toggle-status");

    expect($teacher->refresh()->is_active)->toBeTrue();
});

it('cannot toggle status of non-teacher', function () {
    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/teachers/{$this->student->id}/toggle-status");

    $response->assertNotFound();
});
