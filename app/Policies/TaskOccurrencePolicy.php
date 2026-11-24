<?php

namespace App\Policies;

use App\Models\TaskOccurrence;
use App\Models\User;

class TaskOccurrencePolicy
{
    public function view(User $user, TaskOccurrence $occurrence): bool
    {
        return $user->id === $occurrence->routine->user_id;
    }

    public function update(User $user, TaskOccurrence $occurrence): bool
    {
        return $user->id === $occurrence->routine->user_id;
    }

    public function delete(User $user, TaskOccurrence $occurrence): bool
    {
        return $user->id === $occurrence->routine->user_id;
    }
}
