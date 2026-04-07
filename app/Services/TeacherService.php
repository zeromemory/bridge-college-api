<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\TeacherAccountSetupNotification;
use DomainException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class TeacherService
{
    public function list()
    {
        return User::where('role', 'teacher')
            ->withCount('teachingAssignments')
            ->orderBy('name')
            ->get();
    }

    public function show(User $teacher): User
    {
        return $teacher->load('teachingAssignments.classRoom', 'teachingAssignments.subject');
    }

    public function create(array $data): User
    {
        // Magic-link setup flow: admin creates the teacher without supplying
        // a password. We seed a random placeholder (auto-hashed by the model
        // 'hashed' cast) so the column is never empty, then issue a one-time
        // password reset token via Laravel's built-in broker. The teacher
        // sets their real password by consuming the token.
        $data['role'] = 'teacher';
        $data['password'] = Str::random(64);
        $data['email_verified_at'] = now();
        $data['is_active'] = true;
        $data['password_set_at'] = null;

        $teacher = User::create($data);

        $token = Password::broker()->createToken($teacher);
        $teacher->notify(new TeacherAccountSetupNotification($token));

        Log::info('Teacher created (magic-link setup pending)', ['teacher_id' => $teacher->id]);

        return $teacher;
    }

    /**
     * Issue a fresh setup token and resend the welcome email. Used by admin
     * when a teacher loses or never receives the original setup link.
     * Laravel's broker automatically invalidates the previous token.
     */
    public function resendSetupLink(User $teacher): void
    {
        if (! $teacher->isTeacher()) {
            throw new DomainException('User is not a teacher');
        }

        if ($teacher->password_set_at !== null) {
            throw new DomainException('Teacher has already set their password');
        }

        $token = Password::broker()->createToken($teacher);
        $teacher->notify(new TeacherAccountSetupNotification($token));

        Log::info('Teacher setup link resent', ['teacher_id' => $teacher->id]);
    }

    public function update(User $teacher, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $teacher->update($data);

        Log::info('Teacher updated', ['teacher_id' => $teacher->id]);

        return $teacher->fresh();
    }

    public function toggleStatus(User $teacher): User
    {
        $teacher->update(['is_active' => !$teacher->is_active]);

        Log::info('Teacher status toggled', [
            'teacher_id' => $teacher->id,
            'is_active' => $teacher->is_active,
        ]);

        return $teacher;
    }
}
