<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        return $user->id === $application->user_id;
    }

    public function update(User $user, Application $application): bool
    {
        return $user->id === $application->user_id && $application->status === 'draft';
    }

    public function submit(User $user, Application $application): bool
    {
        return $user->id === $application->user_id && $application->status === 'draft';
    }

    public function uploadDocument(User $user, Application $application): bool
    {
        return $user->id === $application->user_id && $application->status === 'draft';
    }

    public function deleteDocument(User $user, Application $application): bool
    {
        return $user->id === $application->user_id && $application->status === 'draft';
    }
}
