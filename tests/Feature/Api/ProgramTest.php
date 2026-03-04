<?php

use App\Models\Program;

it('lists active programs', function () {
    Program::factory()->create(['name' => 'SSC-I', 'slug' => 'ssc-i', 'is_active' => true, 'sort_order' => 1]);
    Program::factory()->create(['name' => 'SSC-II', 'slug' => 'ssc-ii', 'is_active' => true, 'sort_order' => 2]);
    Program::factory()->create(['name' => 'Inactive', 'slug' => 'inactive', 'is_active' => false, 'sort_order' => 3]);

    $this->getJson('/api/v1/programs')
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonCount(2, 'data.programs')
        ->assertJsonPath('data.programs.0.name', 'SSC-I')
        ->assertJsonPath('data.programs.1.name', 'SSC-II');
});

it('returns programs in sort order', function () {
    Program::factory()->create(['name' => 'Z-Program', 'slug' => 'z-program', 'sort_order' => 3]);
    Program::factory()->create(['name' => 'A-Program', 'slug' => 'a-program', 'sort_order' => 1]);
    Program::factory()->create(['name' => 'M-Program', 'slug' => 'm-program', 'sort_order' => 2]);

    $this->getJson('/api/v1/programs')
        ->assertOk()
        ->assertJsonPath('data.programs.0.name', 'A-Program')
        ->assertJsonPath('data.programs.1.name', 'M-Program')
        ->assertJsonPath('data.programs.2.name', 'Z-Program');
});

it('shows program by slug', function () {
    $program = Program::factory()->create(['name' => 'HSSC-I', 'slug' => 'hssc-i']);

    $this->getJson('/api/v1/programs/hssc-i')
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonPath('data.program.name', 'HSSC-I');
});

it('returns 404 for non-existent program slug', function () {
    $this->getJson('/api/v1/programs/nonexistent')
        ->assertStatus(404)
        ->assertJsonPath('error_code', 'NOT_FOUND');
});

it('returns 404 for inactive program', function () {
    Program::factory()->create(['slug' => 'inactive-prog', 'is_active' => false]);

    $this->getJson('/api/v1/programs/inactive-prog')
        ->assertStatus(404);
});
