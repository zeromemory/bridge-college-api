<?php

use App\Models\User;

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['cnic' => '35202-1234567-1']);

    $this->withSession([])
        ->postJson('/api/v1/login', ['cnic' => '35202-1234567-1', 'password' => 'password'])
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonPath('data.user.cnic', '35202-1234567-1');
});

it('rejects wrong password', function () {
    User::factory()->create(['cnic' => '35202-1234567-1']);

    $this->withSession([])
        ->postJson('/api/v1/login', ['cnic' => '35202-1234567-1', 'password' => 'wrongpassword'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cnic']);
});

it('rejects non-existent CNIC', function () {
    $this->withSession([])
        ->postJson('/api/v1/login', ['cnic' => '99999-9999999-9', 'password' => 'password'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cnic']);
});

it('blocks login for unverified email', function () {
    User::factory()->unverified()->create(['cnic' => '35202-1234567-1']);

    $this->withSession([])
        ->postJson('/api/v1/login', ['cnic' => '35202-1234567-1', 'password' => 'password'])
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'EMAIL_NOT_VERIFIED');
});

it('blocks login for inactive account', function () {
    User::factory()->inactive()->create(['cnic' => '35202-1234567-1']);

    $this->withSession([])
        ->postJson('/api/v1/login', ['cnic' => '35202-1234567-1', 'password' => 'password'])
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'UNAUTHORIZED');
});

it('locks account after 5 failed attempts', function () {
    User::factory()->create(['cnic' => '35202-1234567-1']);

    for ($i = 0; $i < 5; $i++) {
        $this->withSession([])->postJson('/api/v1/login', ['cnic' => '35202-1234567-1', 'password' => 'wrong']);
    }

    $this->withSession([])
        ->postJson('/api/v1/login', ['cnic' => '35202-1234567-1', 'password' => 'password'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cnic']);
});

it('returns EMAIL_NOT_VERIFIED data with email address', function () {
    User::factory()->unverified()->create(['cnic' => '35202-1234567-1', 'email' => 'test@example.com']);

    $this->withSession([])
        ->postJson('/api/v1/login', ['cnic' => '35202-1234567-1', 'password' => 'password'])
        ->assertStatus(403)
        ->assertJsonPath('data.email', 'test@example.com');
});
