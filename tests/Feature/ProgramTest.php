<?php

use App\Models\Program;

it('lists active programs', function () {
    Program::factory()->create(['name' => 'SSC-I', 'slug' => 'ssc-i', 'is_active' => true, 'sort_order' => 1]);
    Program::factory()->create(['name' => 'SSC-II', 'slug' => 'ssc-ii', 'is_active' => true, 'sort_order' => 2]);
    Program::factory()->create(['name' => 'Inactive Program', 'slug' => 'inactive', 'is_active' => false, 'sort_order' => 3]);

    $response = $this->getJson('/api/v1/programs');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.programs')
        ->assertJsonPath('data.programs.0.name', 'SSC-I')
        ->assertJsonPath('data.programs.1.name', 'SSC-II');
});

it('returns programs ordered by sort_order', function () {
    Program::factory()->create(['name' => 'Third', 'slug' => 'third', 'sort_order' => 3]);
    Program::factory()->create(['name' => 'First', 'slug' => 'first', 'sort_order' => 1]);
    Program::factory()->create(['name' => 'Second', 'slug' => 'second', 'sort_order' => 2]);

    $response = $this->getJson('/api/v1/programs');

    $response->assertOk()
        ->assertJsonPath('data.programs.0.name', 'First')
        ->assertJsonPath('data.programs.1.name', 'Second')
        ->assertJsonPath('data.programs.2.name', 'Third');
});

it('shows a program by slug', function () {
    Program::factory()->create(['name' => 'HSSC-I', 'slug' => 'hssc-i', 'level' => 'hssc']);

    $response = $this->getJson('/api/v1/programs/hssc-i');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.program.name', 'HSSC-I')
        ->assertJsonPath('data.program.slug', 'hssc-i')
        ->assertJsonPath('data.program.level', 'hssc');
});

it('returns 404 for non-existent program slug', function () {
    $response = $this->getJson('/api/v1/programs/does-not-exist');

    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'NOT_FOUND');
});

it('returns 404 for inactive program slug', function () {
    Program::factory()->create(['slug' => 'hidden', 'is_active' => false]);

    $response = $this->getJson('/api/v1/programs/hidden');

    $response->assertNotFound()
        ->assertJsonPath('success', false);
});

it('returns empty array when no active programs exist', function () {
    Program::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/programs');

    $response->assertOk()
        ->assertJsonCount(0, 'data.programs');
});
