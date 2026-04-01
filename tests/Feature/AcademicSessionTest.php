<?php

use App\Models\AcademicSession;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->student = User::factory()->create();
});

// ── Auth ──

it('denies student access to academic sessions', function () {
    $this->actingAs($this->student)->getJson('/api/v1/admin/academic-sessions')
        ->assertForbidden();
});

it('denies unauthenticated access to academic sessions', function () {
    $this->getJson('/api/v1/admin/academic-sessions')
        ->assertUnauthorized();
});

// ── List ──

it('lists academic sessions', function () {
    AcademicSession::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/academic-sessions');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

// ── Create ──

it('creates an academic session', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/academic-sessions', [
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', '2026-2027');

    $this->assertDatabaseHas('academic_sessions', ['name' => '2026-2027']);
});

it('validates required fields when creating session', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/academic-sessions', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'start_date', 'end_date']);
});

it('validates end_date is after start_date', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/academic-sessions', [
        'name' => '2026-2027',
        'start_date' => '2027-04-01',
        'end_date' => '2026-03-31',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});

it('validates unique session name', function () {
    AcademicSession::factory()->create(['name' => '2026-2027']);

    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/academic-sessions', [
        'name' => '2026-2027',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

// ── Update ──

it('updates an academic session', function () {
    $session = AcademicSession::factory()->create();

    $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/academic-sessions/{$session->id}", [
        'name' => 'Updated Session',
        'start_date' => '2026-04-01',
        'end_date' => '2027-03-31',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Session');
});

// ── Activate ──

it('activates a session and deactivates others', function () {
    $session1 = AcademicSession::factory()->active()->create();
    $session2 = AcademicSession::factory()->create();

    expect($session1->is_active)->toBeTrue()
        ->and($session2->is_active)->toBeFalse();

    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/academic-sessions/{$session2->id}/activate");

    $response->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect($session1->refresh()->is_active)->toBeFalse()
        ->and($session2->refresh()->is_active)->toBeTrue();
});
