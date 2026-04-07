<?php

use App\Models\User;
use App\Notifications\TeacherAccountSetupNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('full magic-link setup flow: admin creates teacher → teacher sets password → can log in', function () {
    Notification::fake();

    // Admin creates teacher (no password supplied)
    $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/admin/teachers', [
        'name' => 'Ms. Fatima',
        'email' => 'fatima@bci.edu.pk',
        'cnic' => '35202-7654321-2',
        'mobile' => '03001234567',
    ]);
    $createResponse->assertCreated();

    $teacher = User::where('email', 'fatima@bci.edu.pk')->firstOrFail();
    expect($teacher->password_set_at)->toBeNull();

    // Capture the token sent in the setup email
    $capturedToken = null;
    Notification::assertSentTo(
        $teacher,
        TeacherAccountSetupNotification::class,
        function (TeacherAccountSetupNotification $notification) use (&$capturedToken) {
            $capturedToken = $notification->token;

            return true;
        },
    );
    expect($capturedToken)->not->toBeNull();

    // Logout admin so we can hit reset-password as a guest
    auth()->guard('web')->logout();

    // Teacher consumes the magic link → POSTs to /reset-password
    $resetResponse = $this->postJson('/api/v1/reset-password', [
        'email' => $teacher->email,
        'token' => $capturedToken,
        'password' => 'NewSecurePass123!',
        'password_confirmation' => 'NewSecurePass123!',
    ]);
    $resetResponse->assertOk();

    $teacher->refresh();
    expect($teacher->password_set_at)->not->toBeNull();
});

it('rejects an expired/invalid setup token with INVALID_TOKEN error_code', function () {
    $teacher = User::factory()->teacher()->create([
        'email' => 'expired@bci.edu.pk',
        'password_set_at' => null,
    ]);

    $response = $this->postJson('/api/v1/reset-password', [
        'email' => $teacher->email,
        'token' => 'totally-fake-token',
        'password' => 'AnyPass123!',
        'password_confirmation' => 'AnyPass123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error_code', 'INVALID_TOKEN');
});

it('the same setup token cannot be used twice', function () {
    Notification::fake();

    $teacher = User::factory()->teacher()->create([
        'email' => 'twouse@bci.edu.pk',
        'password_set_at' => null,
    ]);

    $token = Password::broker()->createToken($teacher);

    // First use → success
    $this->postJson('/api/v1/reset-password', [
        'email' => $teacher->email,
        'token' => $token,
        'password' => 'FirstPass123!',
        'password_confirmation' => 'FirstPass123!',
    ])->assertOk();

    // Second use → fails
    $this->postJson('/api/v1/reset-password', [
        'email' => $teacher->email,
        'token' => $token,
        'password' => 'SecondPass123!',
        'password_confirmation' => 'SecondPass123!',
    ])->assertUnprocessable()
        ->assertJsonPath('error_code', 'INVALID_TOKEN');
});

// ── Resend setup link ──

it('admin can resend the setup link to a teacher who has not set their password', function () {
    Notification::fake();

    $teacher = User::factory()->teacher()->create([
        'password_set_at' => null,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/teachers/{$teacher->id}/resend-setup");

    $response->assertOk();
    Notification::assertSentTo($teacher, TeacherAccountSetupNotification::class);
});

it('admin cannot resend setup link once teacher has set their password', function () {
    Notification::fake();

    $teacher = User::factory()->teacher()->create([
        'password_set_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/teachers/{$teacher->id}/resend-setup");

    $response->assertStatus(409);
    Notification::assertNothingSent();
});

it('resend-setup endpoint returns 404 for non-teacher users', function () {
    $student = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/teachers/{$student->id}/resend-setup");

    $response->assertNotFound();
});

it('students cannot call the resend-setup endpoint', function () {
    $student = User::factory()->create();
    $teacher = User::factory()->teacher()->create(['password_set_at' => null]);

    $this->actingAs($student)
        ->postJson("/api/v1/admin/teachers/{$teacher->id}/resend-setup")
        ->assertForbidden();
});
