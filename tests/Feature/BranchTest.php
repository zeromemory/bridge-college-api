<?php

use App\Models\Branch;

it('lists active branches', function () {
    Branch::factory()->create(['name' => 'Lahore', 'is_active' => true, 'sort_order' => 1]);
    Branch::factory()->create(['name' => 'Islamabad', 'is_active' => true, 'sort_order' => 2]);
    Branch::factory()->create(['name' => 'Hidden', 'is_active' => false, 'sort_order' => 3]);

    $response = $this->getJson('/api/v1/branches');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.branches')
        ->assertJsonPath('data.branches.0.name', 'Lahore')
        ->assertJsonPath('data.branches.1.name', 'Islamabad');
});

it('returns branches ordered by sort_order', function () {
    Branch::factory()->create(['name' => 'Third', 'sort_order' => 3]);
    Branch::factory()->create(['name' => 'First', 'sort_order' => 1]);
    Branch::factory()->create(['name' => 'Second', 'sort_order' => 2]);

    $response = $this->getJson('/api/v1/branches');

    $response->assertOk()
        ->assertJsonPath('data.branches.0.name', 'First')
        ->assertJsonPath('data.branches.1.name', 'Second')
        ->assertJsonPath('data.branches.2.name', 'Third');
});

it('shows a branch by id', function () {
    $branch = Branch::factory()->create([
        'name' => 'Lahore — Shalamar',
        'city' => 'Lahore',
        'phones' => ['042-36831098', '042-36851619'],
    ]);

    $response = $this->getJson("/api/v1/branches/{$branch->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.branch.name', 'Lahore — Shalamar')
        ->assertJsonPath('data.branch.city', 'Lahore')
        ->assertJsonPath('data.branch.phones', ['042-36831098', '042-36851619']);
});

it('returns 404 for non-existent branch', function () {
    $response = $this->getJson('/api/v1/branches/999');

    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'NOT_FOUND');
});

it('returns 404 for inactive branch', function () {
    $branch = Branch::factory()->create(['is_active' => false]);

    $response = $this->getJson("/api/v1/branches/{$branch->id}");

    $response->assertNotFound()
        ->assertJsonPath('success', false);
});

it('returns empty array when no active branches exist', function () {
    Branch::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/branches');

    $response->assertOk()
        ->assertJsonCount(0, 'data.branches');
});

it('returns phones as array in branch response', function () {
    Branch::factory()->create([
        'phones' => ['042-36831098'],
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/branches');

    $response->assertOk();
    $phones = $response->json('data.branches.0.phones');
    expect($phones)->toBeArray();
});
