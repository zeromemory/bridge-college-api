<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use App\Notifications\ResetPasswordNotification;

it('sends password reset link for valid CNIC', function () {
    Notification::fake();
    $user = User::factory()->create(['cnic' => '35202-1234567-1']);

    $this->postJson('/api/v1/forgot-password', ['cnic' => '35202-1234567-1'])
        ->assertOk()
        ->assertJson(['success' => true]);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('does not leak user existence on forgot password', function () {
    $this->postJson('/api/v1/forgot-password', ['cnic' => '99999-9999999-9'])
        ->assertOk()
        ->assertJson(['success' => true]);
});

it('resets password with valid token', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $token = Password::createToken($user);

    $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => 'test@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});

it('rejects reset with invalid token', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/v1/reset-password', [
        'token' => 'invalid-token',
        'email' => 'test@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])
        ->assertStatus(422);
});

it('rejects reset with wrong email', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $token = Password::createToken($user);

    $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => 'wrong@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])
        ->assertStatus(422);
});

it('rejects reset with weak password', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $token = Password::createToken($user);

    $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => 'test@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});
