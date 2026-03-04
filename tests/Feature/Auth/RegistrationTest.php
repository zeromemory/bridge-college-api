<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\VerifyEmailNotification;

beforeEach(function () {
    Notification::fake();
});

$validData = [
    'name' => 'Test Student',
    'email' => 'student@example.com',
    'cnic' => '35202-1234567-1',
    'mobile' => '03001234567',
    'nationality' => 'pakistani',
    'password' => 'password123',
    'password_confirmation' => 'password123',
];

it('registers a new user successfully', function () use (&$validData) {
    $response = $this->postJson('/api/v1/register', $validData);

    $response->assertStatus(201)
        ->assertJson(['success' => true])
        ->assertJsonPath('data.user.email', 'student@example.com')
        ->assertJsonPath('data.user.cnic', '35202-1234567-1')
        ->assertJsonPath('data.user.mobile', '03001234567')
        ->assertJsonPath('data.user.nationality', 'pakistani')
        ->assertJsonPath('data.user.role', 'student');

    $this->assertDatabaseHas('users', ['email' => 'student@example.com', 'cnic' => '35202-1234567-1']);
});

it('sends verification email on registration', function () use (&$validData) {
    $this->postJson('/api/v1/register', $validData)->assertStatus(201);

    $user = User::where('email', 'student@example.com')->first();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('rejects duplicate CNIC', function () use (&$validData) {
    User::factory()->create(['cnic' => '35202-1234567-1']);

    $this->postJson('/api/v1/register', $validData)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cnic']);
});

it('rejects duplicate email', function () use (&$validData) {
    User::factory()->create(['email' => 'student@example.com']);

    $this->postJson('/api/v1/register', $validData)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects invalid CNIC format', function () use (&$validData) {
    $this->postJson('/api/v1/register', [...$validData, 'cnic' => '12345678'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cnic']);
});

it('rejects weak password (less than 8 chars)', function () use (&$validData) {
    $this->postJson('/api/v1/register', [...$validData, 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects mismatched password confirmation', function () use (&$validData) {
    $this->postJson('/api/v1/register', [...$validData, 'password_confirmation' => 'different123'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejects invalid mobile number', function () use (&$validData) {
    $this->postJson('/api/v1/register', [...$validData, 'mobile' => '12345'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['mobile']);
});

it('rejects invalid nationality', function () use (&$validData) {
    $this->postJson('/api/v1/register', [...$validData, 'nationality' => 'american'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nationality']);
});

it('rejects missing required fields', function () {
    $this->postJson('/api/v1/register', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'cnic', 'mobile', 'nationality', 'password']);
});
