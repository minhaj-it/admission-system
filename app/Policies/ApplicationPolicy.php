<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /** A student sees only their own applications; staff need the permission and never see unfinished drafts. */
    public function view(User $user, Application $application): bool
    {
        if ($user->isStudent()) {
            return $application->user_id === $user->id;
        }

        return $user->can('applicant-list.view') && $application->status !== Application::DRAFT;
    }

    /** Editing and submitting: the owner only, and only while the application is open for changes. */
    public function update(User $user, Application $application): bool
    {
        return $user->isStudent() && $application->user_id === $user->id && $application->isEditable();
    }
}
