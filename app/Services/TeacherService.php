<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
        $data['role'] = 'teacher';
        $data['password'] = Hash::make($data['password']);
        $data['email_verified_at'] = now();

        $teacher = User::create($data);

        Log::info('Teacher created', ['teacher_id' => $teacher->id]);

        return $teacher;
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
