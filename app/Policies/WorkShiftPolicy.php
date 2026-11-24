<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkShift;

class WorkShiftPolicy
{
    public function view(User $user, WorkShift $shift): bool
    {
        return $user->id === $shift->user_id;
    }

    public function update(User $user, WorkShift $shift): bool
    {
        return $user->id === $shift->user_id;
    }

    public function delete(User $user, WorkShift $shift): bool
    {
        return $user->id === $shift->user_id;
    }
}
