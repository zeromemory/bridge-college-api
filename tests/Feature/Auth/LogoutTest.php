<?php

use App\Models\User;

it('logs out authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([])
        ->postJson('/api/v1/logout')
        ->assertOk()
        ->assertJson(['success' => true]);
});

it('rejects logout for unauthenticated user', function () {
    $this->postJson('/api/v1/logout')
        ->assertStatus(401);
});

it('returns user profile when authenticated', function () {
    $user = User::factory()->create(['cnic' => '35202-1234567-1']);

    $this->actingAs($user)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.user.cnic', '35202-1234567-1');
});

it('rejects user profile when unauthenticated', function () {
    $this->getJson('/api/v1/user')
        ->assertStatus(401);
});
