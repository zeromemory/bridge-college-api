<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use App\Notifications\VerifyEmailNotification;

it('verifies email with valid signed link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    // Extract query params from signed URL
    $parts = parse_url($url);
    parse_str($parts['query'], $query);

    $this->getJson("/api/v1/email/verify/{$user->id}/" . sha1($user->email) . '?' . http_build_query($query))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects invalid verification hash', function () {
    $user = User::factory()->unverified()->create();

    // Signed middleware rejects tampered URLs with 403
    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $parts = parse_url($url);
    parse_str($parts['query'], $query);

    $this->getJson("/api/v1/email/verify/{$user->id}/invalidhash?" . http_build_query($query))
        ->assertStatus(403);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('returns success for already verified email', function () {
    $user = User::factory()->create(); // already verified

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $parts = parse_url($url);
    parse_str($parts['query'], $query);

    $this->getJson("/api/v1/email/verify/{$user->id}/" . sha1($user->email) . '?' . http_build_query($query))
        ->assertOk()
        ->assertJson(['success' => true]);
});

it('resends verification email', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'test@example.com']);

    $this->postJson('/api/v1/email/resend', ['email' => 'test@example.com'])
        ->assertOk()
        ->assertJson(['success' => true]);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('does not resend to already verified user', function () {
    Notification::fake();
    User::factory()->create(['email' => 'verified@example.com']);

    $this->postJson('/api/v1/email/resend', ['email' => 'verified@example.com'])
        ->assertOk();

    Notification::assertNothingSent();
});

it('does not leak user existence on resend', function () {
    $this->postJson('/api/v1/email/resend', ['email' => 'nonexistent@example.com'])
        ->assertOk()
        ->assertJson(['success' => true]);
});
