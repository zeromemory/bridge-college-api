<?php

use App\Models\Branch;

it('lists active branches', function () {
    Branch::factory()->create(['name' => 'Islamabad', 'is_active' => true, 'sort_order' => 1]);
    Branch::factory()->create(['name' => 'Lahore', 'is_active' => true, 'sort_order' => 2]);
    Branch::factory()->create(['name' => 'Inactive Branch', 'is_active' => false, 'sort_order' => 3]);

    $this->getJson('/api/v1/branches')
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonCount(2, 'data.branches')
        ->assertJsonPath('data.branches.0.name', 'Islamabad');
});

it('returns branches in sort order', function () {
    Branch::factory()->create(['name' => 'C-Branch', 'sort_order' => 3]);
    Branch::factory()->create(['name' => 'A-Branch', 'sort_order' => 1]);
    Branch::factory()->create(['name' => 'B-Branch', 'sort_order' => 2]);

    $this->getJson('/api/v1/branches')
        ->assertOk()
        ->assertJsonPath('data.branches.0.name', 'A-Branch')
        ->assertJsonPath('data.branches.1.name', 'B-Branch')
        ->assertJsonPath('data.branches.2.name', 'C-Branch');
});

it('shows branch by id', function () {
    $branch = Branch::factory()->create(['name' => 'Islamabad Campus']);

    $this->getJson("/api/v1/branches/{$branch->id}")
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonPath('data.branch.name', 'Islamabad Campus');
});

it('returns 404 for non-existent branch', function () {
    $this->getJson('/api/v1/branches/999')
        ->assertStatus(404)
        ->assertJsonPath('error_code', 'NOT_FOUND');
});

it('returns 404 for inactive branch', function () {
    $branch = Branch::factory()->create(['is_active' => false]);

    $this->getJson("/api/v1/branches/{$branch->id}")
        ->assertStatus(404);
});

it('returns phones as array', function () {
    $branch = Branch::factory()->create([
        'phones' => ['042-36831098', '042-36851619'],
    ]);

    $this->getJson("/api/v1/branches/{$branch->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.branch.phones');
});
